<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BookLoan;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Invoice;
use App\Models\NotificationDispatch;
use App\Models\NotificationPreference;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TelegramAccount;
use App\Models\TimetableSlot;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramService
{
    /**
     * In-memory record of sent messages during testing.
     * @var array<int, array>
     */
    public static array $sentMessages = [];

    /**
     * Clear recorded sent messages (used in testing).
     */
    public static function clearSentMessages(): void
    {
        self::$sentMessages = [];
    }

    /**
     * Generate or renew a 7-day link code for a user to bind their Telegram account.
     */
    public function generateLinkCode(User $user): array
    {
        $school = $user->school;
        $botUsername = $school?->telegram_bot_username 
            ?: config('services.telegram.bot_username', 'BinaSchoolsBot');

        $linkCode = 'TG-' . strtoupper(Str::random(8));

        $account = TelegramAccount::updateOrCreate(
            [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
            ],
            [
                'link_code' => $linkCode,
                'link_code_expires_at' => Carbon::now()->addDays(7),
                'notifications_enabled' => true,
            ]
        );

        $deepLink = "https://t.me/{$botUsername}?start={$linkCode}";

        return [
            'link_code' => $linkCode,
            'bot_username' => $botUsername,
            'deep_link' => $deepLink,
            'expires_at' => $account->link_code_expires_at->toIso8601String(),
            'is_linked' => $account->is_linked,
            'telegram_username' => $account->telegram_username,
        ];
    }

    /**
     * Link a user's Telegram account by matching their /start <link-code>.
     */
    public function linkAccountByCode(
        string $linkCode,
        string|int $chatId,
        ?string $username = null,
        ?string $firstName = null
    ): TelegramAccount {
        $account = TelegramAccount::withoutGlobalScopes()
            ->where('link_code', trim($linkCode))
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'link_code' => ['Invalid Telegram link code. Please generate a new code from your portal.'],
            ]);
        }

        if (! $account->isCodeValid()) {
            throw ValidationException::withMessages([
                'link_code' => ['This link code has expired. Please generate a new link code.'],
            ]);
        }

        $school = $account->school ?: School::find($account->school_id);

        return app(TenantManager::class)->runInTenantContext($school, function () use ($account, $chatId, $username, $firstName, $school) {
            $account->update([
                'telegram_chat_id' => (string) $chatId,
                'telegram_username' => $username,
                'first_name' => $firstName,
                'is_linked' => true,
                'linked_at' => Carbon::now(),
                'notifications_enabled' => true,
            ]);

            if ($account->user) {
                $pref = NotificationPreference::forUser($account->user, $account->school_id);
                if (! $pref->telegram_enabled) {
                    $pref->update(['telegram_enabled' => true]);
                }
            }

            $welcomeMessage = $account->user
                ? $this->buildRoleWelcomeMessage($account->user, $school)
                : "👋 <b>Welcome!</b>\n\nYour Telegram account has been successfully linked.";

            $this->sendMessage($school, $chatId, $welcomeMessage, 'HTML', $this->buildRoleKeyboard($account->user));

            return $account;
        });
    }

    /**
     * Link a user's Telegram account by matching their email, phone number, or student admission number.
     */
    public function linkAccountByContact(
        School $school,
        string $identifier,
        string|int $chatId,
        ?string $username = null,
        ?string $firstName = null
    ): TelegramAccount {
        return app(TenantManager::class)->runInTenantContext($school, function () use ($school, $identifier, $chatId, $username, $firstName) {
            $user = $this->findUserByIdentifier($school, $identifier);

            if (! $user) {
                throw ValidationException::withMessages([
                    'identifier' => ["We could not find an active account in {$school->name} matching '{$identifier}'."],
                ]);
            }

            // Unbind any other user linked to this same chat ID in this school
            TelegramAccount::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('telegram_chat_id', (string) $chatId)
                ->where('user_id', '!=', $user->id)
                ->update([
                    'is_linked' => false,
                    'telegram_chat_id' => null,
                ]);

            $account = TelegramAccount::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $account) {
                $account = TelegramAccount::withoutGlobalScopes()->create([
                    'school_id' => $school->id,
                    'user_id' => $user->id,
                    'link_code' => 'TG-' . Str::upper(Str::random(8)),
                    'link_code_expires_at' => Carbon::now()->addDays(30),
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_username' => $username,
                    'first_name' => $firstName,
                    'is_linked' => true,
                    'linked_at' => Carbon::now(),
                    'notifications_enabled' => true,
                ]);
            } else {
                $account->update([
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_username' => $username,
                    'first_name' => $firstName,
                    'is_linked' => true,
                    'linked_at' => Carbon::now(),
                    'notifications_enabled' => true,
                ]);
            }

            // Ensure user's notification preferences enable telegram channel
            $pref = NotificationPreference::forUser($user, $school->id);
            if (! $pref->telegram_enabled) {
                $pref->update(['telegram_enabled' => true]);
            }

            $welcomeMessage = $this->buildRoleWelcomeMessage($user, $school);
            $this->sendMessage($school, $chatId, $welcomeMessage, 'HTML', $this->buildRoleKeyboard($user));

            return $account;
        });
    }

    /**
     * Locate an active user in a school by email, phone, or student admission number.
     */
    public function findUserByIdentifier(School $school, string $identifier): ?User
    {
        $trimmed = trim($identifier);
        if (empty($trimmed)) {
            return null;
        }

        // 1. Direct case-insensitive email match
        if (str_contains($trimmed, '@')) {
            return User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereRaw('LOWER(email) = ?', [strtolower($trimmed)])
                ->first();
        }

        // 2. Phone number match (digits normalization)
        $digits = preg_replace('/[^0-9]/', '', $trimmed);
        if (!empty($digits) && strlen($digits) >= 6) {
            // Check User.phone
            $user = User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereNotNull('phone')
                ->get()
                ->first(fn ($u) => $this->phoneDigitsMatch($digits, (string) $u->phone));

            if ($user) {
                return $user;
            }

            // Check ParentProfile.phone
            $parent = ParentProfile::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereNotNull('phone')
                ->with('user')
                ->get()
                ->first(fn ($p) => $this->phoneDigitsMatch($digits, (string) $p->phone));

            if ($parent && $parent->user) {
                return $parent->user;
            }
        }

        // 3. Admission number match (for students)
        $student = Student::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(admission_number) = ?', [strtolower($trimmed)])
            ->with('user')
            ->first();

        if ($student && $student->user) {
            return $student->user;
        }

        // 4. Fallback search on email without '@'
        return User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(email) = ?', [strtolower($trimmed)])
            ->first();
    }

    /**
     * Check if two phone numbers match by comparing their significant trailing digits.
     */
    protected function phoneDigitsMatch(string $phone1, string $phone2): bool
    {
        $d1 = preg_replace('/[^0-9]/', '', $phone1);
        $d2 = preg_replace('/[^0-9]/', '', $phone2);

        if (empty($d1) || empty($d2)) {
            return false;
        }

        if ($d1 === $d2) {
            return true;
        }

        $minLen = min(strlen($d1), strlen($d2));
        if ($minLen >= 7) {
            return str_ends_with($d1, substr($d2, -7)) || str_ends_with($d2, substr($d1, -7));
        }

        return false;
    }

    /**
     * Construct a personalized welcome message with details tailored to the user's role and enrolled info.
     */
    public function buildRoleWelcomeMessage(User $user, School $school): string
    {
        return app(TenantManager::class)->runInTenantContext($school, function () use ($user, $school) {
            $schoolName = $school->name ?: 'Bina Schools';
            $roleTitle = match (true) {
                $user->isParent() => '👨‍👩‍👧 Parent / Guardian',
                $user->isStudent() => '🎓 Student',
                $user->isTeacher() => '👨‍🏫 Teacher',
                $user->isSchoolAdmin() => '🏫 School Administrator',
                $user->isSuperAdmin() => '👑 Platform Administrator',
                default => '👤 School Member',
            };

            $infoLines = [];
            $this->appendRoleInfoLines($user, $infoLines);

            $details = implode("\n", $infoLines);

            return "✅ <b>Telegram Account Linked Successfully!</b>\n\n"
                . "Welcome, <b>{$user->name}</b>!\n"
                . "• School: <b>{$schoolName}</b>\n"
                . "• Recognized Role: <b>{$roleTitle}</b>\n\n"
                . "{$details}\n\n"
                . "<i>💡 You will now receive automatic notifications for your role directly here. Type /status to view your info or /unlink to disconnect.</i>";
        });
    }

    /**
     * Construct an account status message for a linked user.
     */
    public function buildRoleStatusMessage(User $user, School $school): string
    {
        return app(TenantManager::class)->runInTenantContext($school, function () use ($user, $school) {
            $schoolName = $school->name ?: 'Bina Schools';
            $account = $user->telegramAccount;
            $linkedDate = $account?->linked_at ? $account->linked_at->format('M d, Y') : 'Active';

            $roleTitle = match (true) {
                $user->isParent() => '👨‍👩‍👧 Parent / Guardian',
                $user->isStudent() => '🎓 Student',
                $user->isTeacher() => '👨‍🏫 Teacher',
                $user->isSchoolAdmin() => '🏫 School Administrator',
                $user->isSuperAdmin() => '👑 Platform Administrator',
                default => '👤 School Member',
            };

            $infoLines = [];
            $this->appendRoleInfoLines($user, $infoLines);

            $details = implode("\n", $infoLines);

            return "ℹ️ <b>Your Account Status</b>\n\n"
                . "• User: <b>{$user->name}</b>\n"
                . "• School: <b>{$schoolName}</b>\n"
                . "• Role: <b>{$roleTitle}</b>\n"
                . "• Linked On: <b>{$linkedDate}</b>\n\n"
                . "{$details}\n\n"
                . "<i>Type /unlink to disconnect your Telegram account.</i>";
        });
    }

    /**
     * Append specific info lines and active notification feeds based on the user's role.
     */
    protected function appendRoleInfoLines(User $user, array &$infoLines): void
    {
        if ($user->isParent()) {
            $user->loadMissing(['parentProfile.students.user', 'parentProfile.students.currentSection.gradeLevel']);
            $parent = $user->parentProfile;
            $children = $parent ? $parent->students : collect();

            if ($children->isNotEmpty()) {
                $infoLines[] = "👨‍👩‍👧 <b>Linked Children:</b>";
                foreach ($children as $child) {
                    $secName = $child->currentSection?->name ?: 'Unassigned Section';
                    $gradeName = $child->currentSection?->gradeLevel?->name ?: 'Grade';
                    $childName = $child->user?->name ?: "Student #{$child->admission_number}";
                    $infoLines[] = "• <b>{$childName}</b> ({$gradeName} - {$secName}) [ID: {$child->admission_number}]";
                }
            }

            $infoLines[] = "\n🔔 <b>Active Notifications for You:</b>";
            $infoLines[] = "• 📢 School Announcements & Emergency Bulletins";
            $infoLines[] = "• 🏃 Daily Child Attendance & Absentee Alerts";
            $infoLines[] = "• 📊 Published Term Report Cards & Grades";
            $infoLines[] = "• 🚌 Transport & Route Pick-up / Drop-off Notices";
            $infoLines[] = "• 💬 Direct Messages from Teachers";
        } elseif ($user->isStudent()) {
            $user->loadMissing(['student.currentSection.gradeLevel']);
            $student = $user->student;

            if ($student) {
                $secName = $student->currentSection?->name ?: 'Unassigned Section';
                $gradeName = $student->currentSection?->gradeLevel?->name ?: 'Grade';
                $infoLines[] = "🎓 <b>Student Information:</b>";
                $infoLines[] = "• Admission No: <b>{$student->admission_number}</b>";
                $infoLines[] = "• Class: <b>{$gradeName} - {$secName}</b>";
            }

            $infoLines[] = "\n🔔 <b>Active Notifications for You:</b>";
            $infoLines[] = "• 📢 School Bulletins & Grade-Level Notices";
            $infoLines[] = "• 📅 Class Timetable Updates & Room Changes";
            $infoLines[] = "• 📚 Library Book Due Date Reminders";
            $infoLines[] = "• 🚌 Transport Route Notifications";
        } elseif ($user->isTeacher()) {
            $user->loadMissing(['staff']);
            $staff = $user->staff;

            $infoLines[] = "👨‍🏫 <b>Teacher Information:</b>";
            if ($staff) {
                $dept = $staff->department ?: 'Academic';
                $title = $staff->designation ?: 'Faculty';
                $infoLines[] = "• Position: <b>{$title}</b> ({$dept})";
            }

            $infoLines[] = "\n🔔 <b>Active Notifications for You:</b>";
            $infoLines[] = "• 📢 School & Staff Bulletins";
            $infoLines[] = "• 💬 Direct Messages from Parents";
            $infoLines[] = "• 📅 Teaching Timetable Schedules";
        } else {
            $infoLines[] = "🏫 <b>Administrative Information:</b>";
            $infoLines[] = "• Access Level: <b>" . ($user->isSuperAdmin() ? 'Super Admin' : 'School Admin') . "</b>";
            $infoLines[] = "\n🔔 <b>Active Notifications for You:</b>";
            $infoLines[] = "• 📢 School Announcements & Broadcast Dispatches";
            $infoLines[] = "• ⚙️ System & Operational Notifications";
        }
    }

    /**
     * Unlink a user's Telegram account.
     */
    public function unlinkAccount(User $user): bool
    {
        $account = $user->telegramAccount;
        if (! $account) {
            return false;
        }

        return $account->update([
            'is_linked' => false,
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'linked_at' => null,
        ]);
    }

    /**
     * Generate interactive ReplyKeyboardMarkup for Telegram bot based on user role.
     */
    public function buildRoleKeyboard(?User $user): ?array
    {
        if (! $user) {
            return [
                'keyboard' => [
                    [['text' => '📱 Share Phone Number', 'request_contact' => true]],
                    [['text' => 'ℹ️ How to Link Account']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        if ($user->isParent()) {
            return [
                'keyboard' => [
                    [['text' => '💳 Upload Payment Receipt'], ['text' => '📝 Excuse Child Absence']],
                    [['text' => '📊 Term Report Cards'], ['text' => '🚌 Bus Route']],
                    [['text' => '📢 School Announcements'], ['text' => '💬 Message Teacher']],
                    [['text' => 'ℹ️ My Profile & Children']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        if ($user->isStudent()) {
            return [
                'keyboard' => [
                    [['text' => '📅 My Class Timetable'], ['text' => '📊 My Term Grades']],
                    [['text' => '📚 My Library Books'], ['text' => '🚌 My Bus Route']],
                    [['text' => '📢 School Announcements'], ['text' => 'ℹ️ My Student Profile']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        if ($user->isTeacher()) {
            return [
                'keyboard' => [
                    [['text' => '📅 My Teaching Timetable'], ['text' => '📝 Today\'s Attendance']],
                    [['text' => '📢 School Bulletins'], ['text' => 'ℹ️ My Teacher Profile']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        if ($user->isSchoolAdmin() || $user->isSuperAdmin()) {
            return [
                'keyboard' => [
                    [['text' => '📊 School Summary Metrics'], ['text' => '📢 School Announcements']],
                    [['text' => '⚙️ Bot Settings'], ['text' => 'ℹ️ Admin Profile']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📢 School Announcements'], ['text' => 'ℹ️ My Profile']],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /**
     * Handle incoming Telegram Bot API webhook updates.
     */
    public function handleWebhook(School $school, array $update): array
    {
        return app(TenantManager::class)->runInTenantContext($school, function () use ($school, $update) {
            $message = $update['message'] ?? null;
            if (! $message) {
                return ['status' => 'ignored', 'reason' => 'No message payload'];
            }

            $chatId = $message['chat']['id'] ?? null;
            $text = trim($message['text'] ?? '');
            $username = $message['from']['username'] ?? null;
            $firstName = $message['from']['first_name'] ?? null;
            $contactPhone = $message['contact']['phone_number'] ?? null;
            $photo = $message['photo'] ?? null;
            $document = $message['document'] ?? null;

            if (! $chatId) {
                return ['status' => 'ignored', 'reason' => 'Missing chat ID'];
            }

            // Check if there is an active linked account for this chat ID in this school
            $existingAccount = TelegramAccount::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('telegram_chat_id', (string) $chatId)
                ->where('is_linked', true)
                ->with('user')
                ->first();

            $linkedUser = $existingAccount?->user;

            // 1. Check for /stop, /unlink, /disconnect
            if (in_array(strtolower($text), ['/stop', '/unlink', '/disconnect'])) {
                if ($existingAccount) {
                    $existingAccount->update([
                        'is_linked' => false,
                        'telegram_chat_id' => null,
                    ]);
                    $this->sendMessage($school, $chatId, "ℹ️ Your Telegram account has been disconnected from <b>{$school->name}</b> notifications.\n\nSend /start anytime to connect again.", 'HTML', $this->buildRoleKeyboard(null));
                    return ['status' => 'unlinked'];
                } else {
                    $this->sendMessage($school, $chatId, "ℹ️ No linked account found to disconnect.", 'HTML', $this->buildRoleKeyboard(null));
                    return ['status' => 'not_linked'];
                }
            }

            // 2. If user is UNLINKED: handle /start, contact share, help, or linking credentials
            if (! $linkedUser) {
                // /start [link_code]
                if (Str::startsWith($text, '/start')) {
                    $parts = explode(' ', $text);
                    $code = $parts[1] ?? null;

                    if ($code) {
                        try {
                            $account = $this->linkAccountByCode($code, $chatId, $username, $firstName);
                            return [
                                'status' => 'success',
                                'action' => 'linked',
                                'user_id' => $account->user_id,
                            ];
                        } catch (\Exception $e) {
                            $this->sendMessage($school, $chatId, "❌ " . $e->getMessage(), 'HTML', $this->buildRoleKeyboard(null));
                            return [
                                'status' => 'error',
                                'message' => $e->getMessage(),
                            ];
                        }
                    }

                    // Just /start without code
                    $prompt = "👋 <b>Welcome to {$school->name}!</b>\n\n"
                        . "To link your Telegram account and receive real-time updates tailored to your role (attendance alerts, receipt uploads, published report cards, timetables, and teacher messages):\n\n"
                        . "👉 <b>Please reply with your registered Email Address or Phone Number.</b>\n"
                        . "<i>(Or tap 'Share Phone Number' below)</i>\n\n"
                        . "<i>(Example: parent@example.com or +1 555 733-4663)</i>";

                    $this->sendMessage($school, $chatId, $prompt, 'HTML', $this->buildRoleKeyboard(null));
                    return ['status' => 'prompted_for_contact'];
                }

                // Contact shared via Telegram native button
                if (! empty($contactPhone)) {
                    try {
                        $account = $this->linkAccountByContact($school, $contactPhone, $chatId, $username, $firstName);
                        return [
                            'status' => 'success',
                            'action' => 'linked_by_contact',
                            'user_id' => $account->user_id,
                        ];
                    } catch (\Exception $e) {
                        $this->sendMessage($school, $chatId, "❌ " . $e->getMessage(), 'HTML', $this->buildRoleKeyboard(null));
                        return ['status' => 'error', 'message' => $e->getMessage()];
                    }
                }

                // Help / instructions button
                if ($text === 'ℹ️ How to Link Account') {
                    $howTo = "ℹ️ <b>How to Link Your Account to {$school->name}</b>\n\n"
                        . "1️⃣ <b>Share Phone:</b> Tap '📱 Share Phone Number' below.\n"
                        . "2️⃣ <b>Reply with Email/Phone:</b> Type your registered email address or mobile number in this chat.\n"
                        . "3️⃣ <b>Portal Link:</b> Log in to your school web portal and scan the Telegram connection QR code.\n\n"
                        . "Once linked, you will receive interactive buttons tailored to your role!";
                    $this->sendMessage($school, $chatId, $howTo, 'HTML', $this->buildRoleKeyboard(null));
                    return ['status' => 'how_to_sent'];
                }

                // Reject uploads if unlinked
                if (! empty($photo) || ! empty($document)) {
                    $msg = "ℹ️ Please link your account first before sending receipts or documents.\n\n"
                        . "👉 Reply with your registered <b>Email Address</b> or <b>Phone Number</b> to link.";
                    $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard(null));
                    return ['status' => 'unlinked_upload_rejected'];
                }

                // Any other text: treat as Email, Phone, or Admission Number for linking
                if (! empty($text)) {
                    try {
                        $account = $this->linkAccountByContact($school, $text, $chatId, $username, $firstName);
                        return [
                            'status' => 'success',
                            'action' => 'linked_by_contact',
                            'user_id' => $account->user_id,
                        ];
                    } catch (ValidationException $e) {
                        $errorMsg = "❌ <b>Account Not Found</b>\n\n"
                            . "We could not find an active account in <b>{$school->name}</b> registered with:\n"
                            . "<code>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</code>\n\n"
                            . "👉 Please reply with your exact registered <b>Email Address</b> or <b>Phone Number</b> (e.g. <code>parent@example.com</code> or <code>+1 555 733-4663</code>).\n\n"
                            . "<i>If you need assistance, please contact your school administration.</i>";

                        $this->sendMessage($school, $chatId, $errorMsg, 'HTML', $this->buildRoleKeyboard(null));
                        return [
                            'status' => 'error',
                            'reason' => 'user_not_found',
                            'message' => $e->getMessage(),
                        ];
                    } catch (\Throwable $e) {
                        Log::error("Telegram handleWebhook contact error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                        $this->sendMessage($school, $chatId, "⚠️ <b>Error Linking Account</b>\n\n" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'HTML', $this->buildRoleKeyboard(null));
                        return [
                            'status' => 'error',
                            'reason' => 'server_error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }

                return ['status' => 'no_action'];
            }

            // 3. User is LINKED!
            // 3A. File / Photo / Document Upload (Bank receipts, screenshots, payment slips)
            if (! empty($photo) || ! empty($document)) {
                return $this->handleReceiptUpload($school, $linkedUser, $chatId, $message);
            }

            // 3B. Button actions and text messages
            return $this->handleLinkedUserAction($school, $linkedUser, $chatId, $text, $message);
        });
    }

    /**
     * Process button presses and interactive text inputs from an already-linked user.
     */
    public function handleLinkedUserAction(
        School $school,
        User $user,
        string|int $chatId,
        string $text,
        array $message
    ): array {
        $cleanText = trim($text);
        $lowerText = strtolower($cleanText);

        // 1. Profile / Status buttons or /status
        if (in_array($lowerText, ['/status', '/info', '/myinfo']) ||
            in_array($cleanText, ['ℹ️ My Profile & Children', 'ℹ️ My Student Profile', 'ℹ️ My Teacher Profile', 'ℹ️ Admin Profile', 'ℹ️ My Profile'])) {
            $statusMessage = $this->buildRoleStatusMessage($user, $school);
            $this->sendMessage($school, $chatId, $statusMessage, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'status_sent', 'user_id' => $user->id];
        }

        // 2. Receipt upload button / prompt
        if ($cleanText === '💳 Upload Payment Receipt' || in_array($lowerText, ['/receipt', '/payment', '/upload_receipt'])) {
            return $this->handleParentReceiptPrompt($school, $user, $chatId);
        }

        // 3. Excuse absence button / prompt
        if ($cleanText === '📝 Excuse Child Absence' || in_array($lowerText, ['/excuse', '/absence'])) {
            return $this->handleParentExcusePrompt($school, $user, $chatId);
        }

        // 4. Excuse absence submission (e.g. "Excuse Bart Simpson - Doctor appointment")
        if (Str::startsWith($lowerText, ['excuse ', 'absence: ', 'excuse: ', 'sick: '])) {
            return $this->handleParentExcuseSubmission($school, $user, $chatId, $cleanText);
        }

        // 5. Term report cards / Grades
        if (in_array($cleanText, ['📊 Term Report Cards', '📊 My Term Grades']) || in_array($lowerText, ['/grades', '/reportcard', '/results'])) {
            return $this->handleReportCardsQuery($school, $user, $chatId);
        }

        // 6. School bus / transport route
        if (in_array($cleanText, ['🚌 Bus Route', '🚌 My Bus Route']) || in_array($lowerText, ['/bus', '/transport'])) {
            return $this->handleTransportQuery($school, $user, $chatId);
        }

        // 7. Message teacher prompt
        if ($cleanText === '💬 Message Teacher' || in_array($lowerText, ['/message', '/teacher', '/contact_teacher'])) {
            return $this->handleMessageTeacherPrompt($school, $user, $chatId);
        }

        // 8. Message teacher send (e.g. "Message Teacher: ...")
        if (Str::startsWith($lowerText, ['message teacher:', 'msg teacher:', 'teacher:'])) {
            return $this->handleMessageTeacherSend($school, $user, $chatId, $cleanText);
        }

        // 9. Timetable
        if (in_array($cleanText, ['📅 My Class Timetable', '📅 My Teaching Timetable']) || in_array($lowerText, ['/timetable', '/schedule'])) {
            return $this->handleTimetableQuery($school, $user, $chatId);
        }

        // 10. Today's attendance (teachers)
        if ($cleanText === '📝 Today\'s Attendance' || in_array($lowerText, ['/attendance'])) {
            return $this->handleTeacherAttendanceQuery($school, $user, $chatId);
        }

        // 11. Library books
        if ($cleanText === '📚 My Library Books' || in_array($lowerText, ['/books', '/library'])) {
            return $this->handleLibraryQuery($school, $user, $chatId);
        }

        // 12. School bulletins / announcements
        if (in_array($cleanText, ['📢 School Bulletins', '📢 School Announcements']) || in_array($lowerText, ['/announcements', '/bulletins', '/news'])) {
            return $this->handleSchoolBulletinsQuery($school, $user, $chatId);
        }

        // 13. Admin metrics summary
        if ($cleanText === '📊 School Summary Metrics' || in_array($lowerText, ['/metrics', '/stats', '/dashboard'])) {
            return $this->handleAdminMetricsQuery($school, $user, $chatId);
        }

        // 14. Bot settings
        if ($cleanText === '⚙️ Bot Settings' || in_array($lowerText, ['/settings'])) {
            return $this->handleBotSettingsQuery($school, $user, $chatId);
        }

        // 15. /start when already linked: re-send role menu & welcome
        if (Str::startsWith($lowerText, '/start')) {
            $msg = $this->buildRoleStatusMessage($user, $school);
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'already_linked', 'user_id' => $user->id];
        }

        // 16. Fallback: if user is a parent and text indicates an excuse (contains medical/absence words)
        if ($user->isParent() && preg_match('/\b(sick|fever|doctor|hospital|headache|absent|attendance|staying home|flu)\b/i', $cleanText)) {
            return $this->handleParentExcuseSubmission($school, $user, $chatId, $cleanText);
        }

        // 17. Default response with menu guide
        $msg = "👋 Hello <b>{$user->name}</b>!\n\n"
            . "Please tap any of the menu buttons below to interact with <b>{$school->name}</b>:\n\n"
            . "• Use the interactive keyboard for quick access.\n"
            . "• Type /status to check your linked details.\n"
            . "• Type /unlink to disconnect your Telegram account.";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'menu_sent', 'user_id' => $user->id];
    }

    /**
     * Parent Action: Provide guidance and list unpaid invoices when requesting receipt upload.
     */
    public function handleParentReceiptPrompt(School $school, User $user, string|int $chatId): array
    {
        $parent = $user->parentProfile;
        $children = $parent ? $parent->students()->with('user')->get() : collect();
        $childrenIds = $children->pluck('id')->toArray();

        $invoices = Invoice::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $childrenIds)
            ->whereIn('status', ['unpaid', 'partially_paid', 'partial', 'pending', 'overdue'])
            ->with('student.user')
            ->orderBy('due_date', 'asc')
            ->get();

        $msg = "💳 <b>Upload Bank Receipt or Payment Slip</b>\n\n";

        if ($invoices->isNotEmpty()) {
            $msg .= "<b>Outstanding Invoices for Your Children:</b>\n";
            foreach ($invoices as $inv) {
                $childName = $inv->student?->user?->name ?: "Student #{$inv->student_id}";
                $balance = number_format((float)$inv->total_amount - (float)$inv->paid_amount, 2);
                $dueDate = $inv->due_date ? Carbon::parse($inv->due_date)->format('M d, Y') : 'Due';
                $msg .= "• <b>{$inv->invoice_number}</b> ({$childName}): <b>\${$balance}</b> (Due: {$dueDate})\n";
            }
            $msg .= "\n";
        }

        $msg .= "👉 <b>How to Submit:</b>\n"
            . "Simply <b>send a photo, screenshot, or PDF document</b> of your bank transfer receipt directly in this chat.\n\n"
            . "💡 <i>Tip: Include the invoice number (e.g. INV-XXXX) or child's name in the caption to ensure quick verification!</i>";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'receipt_prompt_sent'];
    }

    /**
     * Parent Action: Process uploaded receipt photo or document and store verification record.
     */
    public function handleReceiptUpload(School $school, User $user, string|int $chatId, array $message): array
    {
        $photo = $message['photo'] ?? null;
        $document = $message['document'] ?? null;
        $caption = trim($message['caption'] ?? '');

        $fileId = null;
        $fileName = 'receipt_' . time();
        if (! empty($photo) && is_array($photo)) {
            $largest = end($photo);
            $fileId = $largest['file_id'] ?? null;
            $fileName = 'receipt_' . ($largest['file_unique_id'] ?? time()) . '.jpg';
        } elseif (! empty($document) && is_array($document)) {
            $fileId = $document['file_id'] ?? null;
            $fileName = $document['file_name'] ?? ('receipt_' . time() . '.pdf');
        }

        if (empty($fileId)) {
            $this->sendMessage($school, $chatId, "⚠️ Could not process the uploaded file. Please try sending it again as a photo or document.", 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'error', 'message' => 'no_file_id'];
        }

        $parent = $user->parentProfile;
        $children = $parent ? $parent->students()->with('user')->get() : collect();
        $childrenIds = $children->pluck('id')->toArray();

        // 1. Check if invoice number is referenced in caption
        $invoice = null;
        if (! empty($caption)) {
            if (preg_match('/(?:INV[-_]?[0-9]+)/i', $caption, $m)) {
                $invoice = Invoice::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->where('invoice_number', strtoupper($m[0]))
                    ->first();
            }
        }

        // 2. Oldest unpaid invoice for parent's children
        if (! $invoice && ! empty($childrenIds)) {
            $invoice = Invoice::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereIn('student_id', $childrenIds)
                ->whereIn('status', ['unpaid', 'partially_paid', 'partial', 'pending', 'overdue'])
                ->orderBy('due_date', 'asc')
                ->first();
        }

        // 3. Fallback: most recent invoice for parent's children
        if (! $invoice && ! empty($childrenIds)) {
            $invoice = Invoice::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereIn('student_id', $childrenIds)
                ->latest('id')
                ->first();
        }

        if ($invoice) {
            $remaining = max(0, (float)$invoice->total_amount - (float)$invoice->paid_amount);
            $payNumber = 'PAY-TG-' . strtoupper(Str::random(6));

            $payment = Payment::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'invoice_id' => $invoice->id,
                'payment_number' => $payNumber,
                'amount' => $remaining > 0 ? $remaining : (float)$invoice->total_amount,
                'method' => 'bank_transfer',
                'paid_at' => Carbon::now(),
                'recorded_by' => $user->id,
                'gateway' => 'telegram',
                'gateway_reference' => (string) $fileId,
                'gateway_status' => 'pending_verification',
                'gateway_metadata' => [
                    'file_id' => $fileId,
                    'file_name' => $fileName,
                    'caption' => $caption,
                    'chat_id' => (string) $chatId,
                    'uploader_name' => $user->name,
                    'uploader_email' => $user->email,
                ],
                'notes' => "Bank payment slip uploaded via Telegram by {$user->name}. Caption: " . ($caption ?: 'None'),
            ]);

            $childName = $invoice->student?->user?->name ?: "Student #{$invoice->student_id}";
            $reply = "✅ <b>Receipt Received Successfully!</b>\n\n"
                . "Thank you, <b>{$user->name}</b>. Your payment receipt has been submitted to the finance office for verification.\n\n"
                . "• Payment Ref: <code>{$payment->payment_number}</code>\n"
                . "• Invoice: <b>{$invoice->invoice_number}</b> ({$childName})\n"
                . "• File: <code>{$fileName}</code>\n"
                . "• Status: <b>⏳ Pending Verification</b>\n\n"
                . "<i>Once verified by school finance, your invoice balance will be updated automatically.</i>";

            $this->sendMessage($school, $chatId, $reply, 'HTML', $this->buildRoleKeyboard($user));
            return [
                'status' => 'success',
                'action' => 'receipt_uploaded',
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        }

        // Generic receipt logging if no invoice was matched
        $reply = "✅ <b>Receipt Received!</b>\n\n"
            . "Thank you, <b>{$user->name}</b>. Your payment receipt (<code>{$fileName}</code>) has been logged and sent to the school finance department for review.\n\n"
            . "• Details: " . ($caption ? htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') : 'Payment receipt upload') . "\n"
            . "• Status: <b>⏳ Pending Finance Review</b>\n\n"
            . "<i>If you know your invoice number, you can reply with it to assist reconciliation.</i>";

        $this->sendMessage($school, $chatId, $reply, 'HTML', $this->buildRoleKeyboard($user));
        return [
            'status' => 'success',
            'action' => 'receipt_uploaded',
            'payment_id' => null,
        ];
    }

    /**
     * Parent Action: Provide guidance on submitting an absence excuse for a child.
     */
    public function handleParentExcusePrompt(School $school, User $user, string|int $chatId): array
    {
        $parent = $user->parentProfile;
        $children = $parent ? $parent->students()->with('user')->get() : collect();

        $sampleChild = $children->first()?->user?->name ?: 'Student';

        $msg = "📝 <b>Submit Student Absence Excuse</b>\n\n"
            . "To notify the school of an upcoming or current absence for your child, please reply in this chat:\n\n"
            . "👉 <b>Format:</b>\n"
            . "<code>Excuse [Child Name] - [Reason]</code>\n\n"
            . "<i>Example:</i>\n"
            . "<code>Excuse {$sampleChild} - Doctor appointment and fever</code>\n\n"
            . "The homeroom teacher and attendance office will record the excused absence automatically.";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'excuse_prompt_sent'];
    }

    /**
     * Parent Action: Record an excused absence in the attendance system.
     */
    public function handleParentExcuseSubmission(School $school, User $user, string|int $chatId, string $text): array
    {
        $user->loadMissing(['parentProfile.students.user', 'parentProfile.students.currentSection.gradeLevel']);
        $parent = $user->parentProfile;
        $children = $parent ? $parent->students : collect();

        if ($children->isEmpty()) {
            $this->sendMessage($school, $chatId, "⚠️ No student is currently linked to your parent profile. Please contact the school administration.", 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'error', 'reason' => 'no_children_linked'];
        }

        $lowerText = strtolower($text);
        $targetChild = null;

        if ($children->count() === 1) {
            $targetChild = $children->first();
        } else {
            // Check if any child name or admission number matches in text
            foreach ($children as $child) {
                $childName = strtolower($child->user?->name ?? '');
                $admNo = strtolower($child->admission_number ?? '');

                if (! empty($admNo) && str_contains($lowerText, $admNo)) {
                    $targetChild = $child;
                    break;
                }

                if (! empty($childName)) {
                    $parts = preg_split('/\s+/', $childName);
                    foreach ($parts as $part) {
                        if (strlen($part) >= 3 && str_contains($lowerText, $part)) {
                            $targetChild = $child;
                            break 2;
                        }
                    }
                }
            }

            if (! $targetChild) {
                $targetChild = $children->first();
            }
        }

        // Extract reason: everything after hyphen, colon, or keywords
        $reason = null;
        if (preg_match('/(?:[-–—:]|because|due to|for)\s*(.+)$/i', $text, $matches)) {
            $reason = trim($matches[1]);
        } else {
            $cleaned = preg_replace('/^(?:excuse|absence|for)\s*(?:[a-z0-9\s]+[-:]\s*)?/i', '', $text);
            $reason = trim($cleaned);
        }

        if (empty($reason)) {
            $reason = "Medical / family reason (reported via Telegram)";
        }

        $today = Carbon::today()->toDateString();
        $sectionId = $targetChild->section_id ?: ($targetChild->current_section_id ?: 1);
        $academicYearId = $targetChild->currentSection?->academic_year_id ?? null;

        $record = AttendanceRecord::withoutGlobalScopes()->updateOrCreate(
            [
                'school_id' => $school->id,
                'student_id' => $targetChild->id,
                'date' => $today,
            ],
            [
                'section_id' => $sectionId,
                'academic_year_id' => $academicYearId,
                'status' => 'excused',
                'marked_by' => $user->id,
                'remarks' => "Excused by parent {$user->name} via Telegram: {$reason}",
            ]
        );

        $childName = $targetChild->user?->name ?: "Student #{$targetChild->admission_number}";
        $dateFormatted = Carbon::today()->format('l, M d, Y');

        $reply = "✅ <b>Absence Excuse Submitted!</b>\n\n"
            . "• Student: <b>{$childName}</b>\n"
            . "• Date: <b>{$dateFormatted}</b>\n"
            . "• Attendance Status: <b>Excused</b>\n"
            . "• Reason: <i>" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</i>\n\n"
            . "The homeroom teacher and school attendance records have been updated.";

        $this->sendMessage($school, $chatId, $reply, 'HTML', $this->buildRoleKeyboard($user));

        return [
            'status' => 'success',
            'action' => 'excuse_submitted',
            'record_id' => $record->id,
            'student_id' => $targetChild->id,
        ];
    }

    /**
     * Query published report cards for parents and students.
     */
    public function handleReportCardsQuery(School $school, User $user, string|int $chatId): array
    {
        $studentIds = [];
        if ($user->isParent()) {
            $parent = $user->parentProfile;
            $studentIds = $parent ? $parent->students()->pluck('students.id')->toArray() : [];
        } elseif ($user->isStudent()) {
            if ($user->student) {
                $studentIds = [$user->student->id];
            }
        }

        if (empty($studentIds)) {
            $msg = "📊 <b>Term Report Cards</b>\n\nℹ️ No linked student found for report card queries.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'empty'];
        }

        $cards = ReportCard::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'published')
            ->with(['student.user', 'term'])
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        if ($cards->isEmpty()) {
            $msg = "📊 <b>Term Report Cards</b>\n\n"
                . "ℹ️ No published report cards are currently available for your student(s).\n"
                . "Report cards will appear here as soon as academic terms are finalized and published.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'no_report_cards'];
        }

        $msg = "📊 <b>Published Term Report Cards</b>\n\n";
        foreach ($cards as $card) {
            $childName = $card->student?->user?->name ?: "Student #{$card->student_id}";
            $termName = $card->term?->name ?: 'Term';
            $grade = $card->overall_grade ?: 'N/A';
            $gpa = $card->gpa !== null ? number_format((float)$card->gpa, 2) : 'N/A';
            $pct = $card->average_percentage !== null ? number_format((float)$card->average_percentage, 1) . '%' : 'N/A';
            $rank = $card->rank_in_section ? "Rank: #{$card->rank_in_section}" : null;
            $remarks = $card->homeroom_remarks ? "\n<i>Remarks: " . htmlspecialchars($card->homeroom_remarks, ENT_QUOTES, 'UTF-8') . "</i>" : '';

            $msg .= "🎓 <b>{$childName}</b> — <b>{$termName}</b>\n"
                . "• Grade: <b>{$grade}</b> | GPA: <b>{$gpa}</b> | Avg: <b>{$pct}</b>\n"
                . ($rank ? "• {$rank}\n" : '')
                . "{$remarks}\n\n";
        }

        $this->sendMessage($school, $chatId, trim($msg), 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'report_cards_sent', 'count' => $cards->count()];
    }

    /**
     * Query assigned bus / transport route for parents and students.
     */
    public function handleTransportQuery(School $school, User $user, string|int $chatId): array
    {
        $studentIds = [];
        if ($user->isParent()) {
            $parent = $user->parentProfile;
            $studentIds = $parent ? $parent->students()->pluck('students.id')->toArray() : [];
        } elseif ($user->isStudent()) {
            if ($user->student) {
                $studentIds = [$user->student->id];
            }
        }

        if (empty($studentIds)) {
            $msg = "🚌 <b>School Bus / Transport Route</b>\n\nℹ️ No linked student found for transport queries.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'empty'];
        }

        $transports = StudentTransport::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $studentIds)
            ->with(['route', 'stop', 'student.user'])
            ->get();

        if ($transports->isEmpty()) {
            $msg = "🚌 <b>School Transport Route & Schedule</b>\n\n"
                . "ℹ️ No active transport route is currently assigned.\n"
                . "Please contact your school transport coordinator to register a route.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'no_transport'];
        }

        $msg = "🚌 <b>School Transport Route & Schedule</b>\n\n";
        foreach ($transports as $tr) {
            $childName = $tr->student?->user?->name ?: "Student #{$tr->student_id}";
            $route = $tr->route;
            $stop = $tr->stop;
            $routeName = $route?->name ?: 'School Route';
            $vehicle = $route?->vehicle_info ?: 'Bus';
            $driver = $route?->driver_name ?: 'Driver';
            $contact = $route?->driver_contact ?: 'School Office';
            $stopName = $stop?->stop_name ?: 'Designated Stop';
            $pickup = $stop?->pickup_time ?: 'Morning';
            $dropoff = $stop?->dropoff_time ?: 'Afternoon';

            $msg .= "👤 <b>Student:</b> {$childName}\n"
                . "• Route: <b>{$routeName}</b> ({$vehicle})\n"
                . "• Driver: <b>{$driver}</b> ({$contact})\n"
                . "• Stop: <b>{$stopName}</b>\n"
                . "• 🌅 Morning Pick-up: <b>{$pickup}</b>\n"
                . "• 🌇 Afternoon Drop-off: <b>{$dropoff}</b>\n\n";
        }

        $this->sendMessage($school, $chatId, trim($msg), 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'transport_sent', 'count' => $transports->count()];
    }

    /**
     * Parent Action: Provide guidance on sending a direct message to teachers.
     */
    public function handleMessageTeacherPrompt(School $school, User $user, string|int $chatId): array
    {
        $msg = "💬 <b>Contact Class Teacher</b>\n\n"
            . "You can send direct messages to teachers through this bot.\n\n"
            . "👉 <b>How to message:</b>\n"
            . "Type your message starting with <code>Message Teacher: </code>\n\n"
            . "<i>Example:</i>\n"
            . "<code>Message Teacher: Bart will need to leave 30 minutes early tomorrow for an appointment.</code>\n\n"
            . "Your message will be sent directly to the student's communication thread in the school portal.";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'message_teacher_prompt_sent'];
    }

    /**
     * Parent Action: Deliver a parent message to the student's communication thread.
     */
    public function handleMessageTeacherSend(School $school, User $user, string|int $chatId, string $text): array
    {
        $parent = $user->parentProfile;
        $children = $parent ? $parent->students()->with('user')->get() : collect();
        $targetChild = $children->first();

        if (! $targetChild) {
            $this->sendMessage($school, $chatId, "⚠️ No linked child found to route your message to teachers.", 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'error', 'reason' => 'no_student'];
        }

        $body = preg_replace('/^(?:message|msg)?\s*teacher\s*[:\-\s]*/i', '', $text);
        $body = trim($body);

        if (empty($body)) {
            $this->sendMessage($school, $chatId, "⚠️ Please include your message text after 'Message Teacher:'.", 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'error', 'reason' => 'empty_body'];
        }

        $thread = CommunicationThread::withoutGlobalScopes()->firstOrCreate(
            [
                'school_id' => $school->id,
                'student_id' => $targetChild->id,
                'created_by' => $user->id,
                'status' => 'active',
            ],
            [
                'subject' => "Telegram message regarding " . ($targetChild->user?->name ?: 'Student'),
                'last_message_at' => Carbon::now(),
            ]
        );

        $thread->update(['last_message_at' => Carbon::now()]);

        $msgModel = CommunicationMessage::create([
            'school_id' => $school->id,
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'body' => $body,
        ]);

        $childName = $targetChild->user?->name ?: "Student #{$targetChild->admission_number}";
        $reply = "✅ <b>Message Sent to Teacher!</b>\n\n"
            . "• Regarding: <b>{$childName}</b>\n"
            . "• Content: <i>" . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . "</i>\n\n"
            . "The teacher will receive your message in the school communication portal.";

        $this->sendMessage($school, $chatId, $reply, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'teacher_message_sent', 'message_id' => $msgModel->id];
    }

    /**
     * Query timetable schedule for students and teachers.
     */
    public function handleTimetableQuery(School $school, User $user, string|int $chatId): array
    {
        $slots = collect();

        if ($user->isStudent()) {
            $student = $user->student;
            $sectionId = $student?->section_id ?: ($student?->current_section_id);
            if ($sectionId) {
                $slots = TimetableSlot::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->where('section_id', $sectionId)
                    ->with(['subject', 'teacher'])
                    ->orderBy('day_of_week')
                    ->orderBy('period_number')
                    ->get();
            }
        } elseif ($user->isTeacher()) {
            $slots = TimetableSlot::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('teacher_id', $user->id)
                ->with(['subject', 'section.gradeLevel'])
                ->orderBy('day_of_week')
                ->orderBy('period_number')
                ->get();
        }

        if ($slots->isEmpty()) {
            $msg = "📅 <b>Class Timetable</b>\n\nℹ️ No active timetable slots scheduled yet.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'no_slots'];
        }

        $msg = "📅 <b>Class Timetable & Schedule</b>\n\n";
        $grouped = $slots->groupBy(fn ($s) => ucfirst($s->day_of_week instanceof \BackedEnum ? $s->day_of_week->value : (string)$s->day_of_week));

        foreach ($grouped as $day => $daySlots) {
            $msg .= "📌 <b>{$day}:</b>\n";
            foreach ($daySlots as $slot) {
                $subj = $slot->subject?->name ?: 'Subject';
                $start = $slot->start_time ? substr($slot->start_time, 0, 5) : "P{$slot->period_number}";
                $end = $slot->end_time ? substr($slot->end_time, 0, 5) : '';
                $time = $end ? "({$start}-{$end})" : "({$start})";
                $room = $slot->room ? "[{$slot->room}]" : '';
                $msg .= "  • Period {$slot->period_number}: <b>{$subj}</b> {$time} {$room}\n";
            }
            $msg .= "\n";
        }

        $this->sendMessage($school, $chatId, trim($msg), 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'timetable_sent', 'count' => $slots->count()];
    }

    /**
     * Query library loans for students and parents.
     */
    public function handleLibraryQuery(School $school, User $user, string|int $chatId): array
    {
        $studentIds = [];
        if ($user->isStudent() && $user->student) {
            $studentIds[] = $user->student->id;
        } elseif ($user->isParent() && $user->parentProfile) {
            $studentIds = $user->parentProfile->students()->pluck('students.id')->toArray();
        }

        $loans = BookLoan::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where(function ($q) use ($user, $studentIds) {
                $q->where('user_id', $user->id);
                if (! empty($studentIds)) {
                    $q->orWhereIn('student_id', $studentIds);
                }
            })
            ->whereIn('status', ['borrowed', 'overdue'])
            ->with('book')
            ->orderBy('due_at', 'asc')
            ->get();

        if ($loans->isEmpty()) {
            $msg = "📚 <b>Library Books & Circulation</b>\n\nℹ️ You have no currently borrowed library books on record.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'no_loans'];
        }

        $msg = "📚 <b>Your Borrowed Library Books</b>\n\n";
        foreach ($loans as $loan) {
            $title = $loan->book?->title ?: 'Book';
            $dueDate = $loan->due_at ? Carbon::parse($loan->due_at)->format('M d, Y') : 'Soon';
            $isOverdue = $loan->status === 'overdue' || (Carbon::parse($loan->due_at)->isPast());
            $tag = $isOverdue ? '⚠️ OVERDUE' : '✅ Active';

            $msg .= "📖 <b>{$title}</b>\n"
                . "• Status: <b>{$tag}</b>\n"
                . "• Due Date: <b>{$dueDate}</b>\n\n";
        }

        $this->sendMessage($school, $chatId, trim($msg), 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'library_sent', 'count' => $loans->count()];
    }

    /**
     * Teacher Action: Summarize today's attendance for teacher's school sections.
     */
    public function handleTeacherAttendanceQuery(School $school, User $user, string|int $chatId): array
    {
        $today = Carbon::today()->toDateString();
        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('date', $today)
            ->get();

        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();
        $excused = $records->where('status', 'excused')->count();
        $total = $records->count();

        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        $dateStr = Carbon::today()->format('l, M d, Y');

        $msg = "📝 <b>Today's Attendance Summary</b>\n"
            . "📅 <b>{$dateStr}</b>\n\n"
            . "• Total Marked: <b>{$total}</b>\n"
            . "• Present: <b>{$present}</b> ({$rate}%)\n"
            . "• Absent: <b>{$absent}</b>\n"
            . "• Late: <b>{$late}</b>\n"
            . "• Excused: <b>{$excused}</b>\n\n"
            . "<i>To mark or adjust class attendance, please visit your teacher portal.</i>";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'attendance_summary_sent'];
    }

    /**
     * Query recent announcements and school bulletins.
     */
    public function handleSchoolBulletinsQuery(School $school, User $user, string|int $chatId): array
    {
        $query = Announcement::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereNotNull('published_at');

        if (! $user->isSuperAdmin() && ! $user->isSchoolAdmin()) {
            $query->published()->forUser($user);
        }

        $announcements = $query->latest('published_at')
            ->take(5)
            ->get();

        if ($announcements->isEmpty()) {
            $msg = "📢 <b>School Announcements</b>\n\nℹ️ No active bulletins published at this time.";
            $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
            return ['status' => 'no_announcements'];
        }

        $msg = "📢 <b>Recent School Bulletins & Announcements</b>\n\n";
        foreach ($announcements as $ann) {
            $date = $ann->published_at ? Carbon::parse($ann->published_at)->format('M d, Y') : 'Recent';
            $snippet = Str::limit(strip_tags($ann->body), 140);
            $msg .= "📌 <b>{$ann->title}</b> <i>({$date})</i>\n"
                . "{$snippet}\n\n";
        }

        $this->sendMessage($school, $chatId, trim($msg), 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'bulletins_sent', 'count' => $announcements->count()];
    }

    /**
     * Admin Action: Display quick school summary metrics.
     */
    public function handleAdminMetricsQuery(School $school, User $user, string|int $chatId): array
    {
        $studentsCount = Student::withoutGlobalScopes()->where('school_id', $school->id)->where('status', 'active')->count();
        $staffCount = Staff::withoutGlobalScopes()->where('school_id', $school->id)->where('status', 'active')->count();
        $unpaidInvoices = Invoice::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->count();
        $todayAttendance = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('date', Carbon::today()->toDateString())
            ->count();

        $msg = "📊 <b>{$school->name} — Summary Metrics</b>\n\n"
            . "• Active Students: <b>{$studentsCount}</b>\n"
            . "• Active Faculty / Staff: <b>{$staffCount}</b>\n"
            . "• Invoices Pending / Overdue: <b>{$unpaidInvoices}</b>\n"
            . "• Today's Marked Attendance: <b>{$todayAttendance} records</b>\n\n"
            . "<i>For complete reporting, visit the administrator web dashboard.</i>";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'metrics_sent'];
    }

    /**
     * Admin Action: View bot credentials and webhook status.
     */
    public function handleBotSettingsQuery(School $school, User $user, string|int $chatId): array
    {
        $username = $school->telegram_bot_username ?: 'Configured Bot';
        $msg = "⚙️ <b>Telegram Bot Settings</b>\n\n"
            . "• School: <b>{$school->name}</b>\n"
            . "• Bot Username: <b>@{$username}</b>\n"
            . "• Status: <b>Active & Listening</b> ✅\n"
            . "• Webhook: <code>" . url("/api/telegram/webhook/{$school->id}") . "</code>\n\n"
            . "<i>To change bot credentials or reconnect webhooks, log in as School Admin to the School Settings portal.</i>";

        $this->sendMessage($school, $chatId, $msg, 'HTML', $this->buildRoleKeyboard($user));
        return ['status' => 'success', 'action' => 'bot_settings_sent'];
    }

    /**
     * Send a Telegram message via Bot API (or records it during testing).
     */
    public function sendMessage(
        School|int|null $school,
        string|int $chatId,
        string $text,
        string $parseMode = 'HTML',
        ?array $replyMarkup = null
    ): bool {
        $record = [
            'chat_id' => (string) $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];
        if (! empty($replyMarkup)) {
            $record['reply_markup'] = $replyMarkup;
        }
        $record['sent_at'] = Carbon::now()->toIso8601String();

        // Record message in mock storage for verification and test assertions
        self::$sentMessages[] = $record;

        // Resolve bot token
        $schoolModel = $school instanceof School ? $school : ($school ? School::find($school) : null);
        $token = $schoolModel?->telegram_bot_token ?: config('services.telegram.bot_token');

        // If in test environment or mock token, skip outbound HTTP network call
        if (app()->environment('testing') || empty($token) || str_starts_with($token, 'fake_') || str_starts_with($token, 'mock_')) {
            return true;
        }

        try {
            $postData = [
                'chat_id' => (string) $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];
            if (! empty($replyMarkup)) {
                $postData['reply_markup'] = $replyMarkup;
            }

            $response = Http::timeout(20)->retry(2, 500)->post("https://api.telegram.org/bot{$token}/sendMessage", $postData);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Telegram message dispatch failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deliver an announcement to a user's Telegram account with deduplication protection.
     * Acceptance criterion 3: A parent who links their Telegram account receives the same announcement
     * via Telegram as they would via email, without duplicate spam if both channels are enabled.
     */
    public function dispatchAnnouncement(Announcement $announcement, User $recipient): bool
    {
        $account = $recipient->telegramAccount;
        if (! $account || ! $account->is_linked || ! $account->telegram_chat_id || ! $account->notifications_enabled) {
            return false;
        }

        // Check deduplication in database: avoid sending duplicate telegram messages
        $alreadySent = NotificationDispatch::withoutGlobalScopes()
            ->where('school_id', $announcement->school_id)
            ->where('notifiable_type', Announcement::class)
            ->where('notifiable_id', $announcement->id)
            ->where('user_id', $recipient->id)
            ->where('channel', 'telegram')
            ->exists();

        if ($alreadySent) {
            return false; // Skip duplicate send
        }

        $schoolName = $announcement->school?->name ?: 'Bina Schools';
        $authorName = $announcement->author?->name ?: 'School Administration';
        $dateStr = $announcement->published_at ? $announcement->published_at->format('M d, Y H:i') : now()->format('M d, Y');

        $message = "📢 <b>{$schoolName} Announcement</b>\n"
            . "<b>{$announcement->title}</b>\n\n"
            . "{$announcement->body}\n\n"
            . "<i>Published: {$dateStr} by {$authorName}</i>";

        $sent = $this->sendMessage(
            $announcement->school_id,
            $account->telegram_chat_id,
            $message
        );

        if ($sent) {
            NotificationDispatch::create([
                'school_id' => $announcement->school_id,
                'notifiable_type' => Announcement::class,
                'notifiable_id' => $announcement->id,
                'user_id' => $recipient->id,
                'channel' => 'telegram',
                'recipient_address' => $account->telegram_chat_id,
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        }

        return $sent;
    }

    /**
     * Test a Telegram Bot token by fetching bot details via getMe.
     */
    public function testBotToken(string $token): array
    {
        $token = trim($token);
        if (empty($token)) {
            return [
                'ok' => false,
                'description' => 'Bot token is empty.',
            ];
        }

        // In test environment or mock tokens, return realistic mock response without outbound HTTP
        if (app()->environment('testing') || str_starts_with($token, 'fake_') || str_starts_with($token, 'mock_') || str_starts_with($token, 'test_')) {
            return [
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Bina Schools Bot',
                    'username' => 'BinaSchoolsBot',
                    'can_join_groups' => true,
                    'can_read_all_group_messages' => false,
                    'supports_inline_queries' => false,
                ],
            ];
        }

        try {
            $response = Http::timeout(6)->get("https://api.telegram.org/bot{$token}/getMe");
            return $response->json() ?? ['ok' => false, 'description' => 'Invalid response from Telegram API.'];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'description' => 'Failed to connect to Telegram API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Register or update the school's webhook URL with the Telegram Bot API.
     */
    public function registerWebhook(School $school): array
    {
        $token = $school->telegram_bot_token;
        if (empty($token)) {
            return [
                'ok' => false,
                'description' => 'Cannot register webhook: school has no Telegram bot token configured.',
            ];
        }

        $webhookUrl = url("/api/telegram/webhook/{$school->id}");

        // In test environment or mock tokens, return simulated success
        if (app()->environment('testing') || str_starts_with($token, 'fake_') || str_starts_with($token, 'mock_') || str_starts_with($token, 'test_')) {
            return [
                'ok' => true,
                'description' => 'Webhook was set successfully.',
                'url' => $webhookUrl,
            ];
        }

        try {
            $response = Http::timeout(6)->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'drop_pending_updates' => false,
            ]);
            $json = $response->json() ?? [];
            $json['url'] = $webhookUrl;
            return $json;
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'description' => 'Failed to register webhook: ' . $e->getMessage(),
                'url' => $webhookUrl,
            ];
        }
    }
}
