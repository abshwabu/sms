<template>
  <div class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>Library Management &amp; Circulation</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
            Catalog &amp; Loans
          </span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">
          Catalog books, issue student and staff loans, track overdue circulation, and collect fines.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          v-if="canManage"
          @click="openCheckoutModal()"
          class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-sm active:scale-[0.98]"
        >
          <span>📖</span>
          <span>Check Out Book</span>
        </button>
        <button
          v-if="canManage"
          @click="openAddBookModal"
          class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs border border-slate-700 transition flex items-center gap-1.5 active:scale-[0.98]"
        >
          <span>+</span>
          <span>New Book Title</span>
        </button>
      </div>
    </div>

    <!-- Feedback Banner -->
    <div v-if="libraryStore.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ libraryStore.error }}</span>
      </div>
      <button @click="libraryStore.clearMessages" class="text-rose-400 hover:text-rose-200">✕</button>
    </div>
    <div v-if="libraryStore.successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span>✓</span>
        <span>{{ libraryStore.successMessage }}</span>
      </div>
      <button @click="libraryStore.clearMessages" class="text-emerald-400 hover:text-emerald-200">✕</button>
    </div>

    <!-- Summary Metrics Cards -->
    <div v-if="libraryStore.summary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-slate-400">Total Titles</span>
        <div class="text-xl font-bold text-white mt-1">{{ libraryStore.summary.total_books }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-slate-400">Total Copies</span>
        <div class="text-xl font-bold text-white mt-1">{{ libraryStore.summary.total_copies }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-emerald-400">Available</span>
        <div class="text-xl font-bold text-emerald-400 mt-1">{{ libraryStore.summary.available_copies }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-blue-400">Active Loans</span>
        <div class="text-xl font-bold text-blue-400 mt-1">{{ libraryStore.summary.active_loans }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-rose-400">Overdue</span>
        <div class="text-xl font-bold text-rose-400 mt-1">{{ libraryStore.summary.overdue_loans }}</div>
      </div>
      <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5">
        <span class="text-[11px] font-medium text-amber-400">Unpaid Fines</span>
        <div class="text-xl font-bold text-amber-400 mt-1 font-mono">${{ Number(libraryStore.summary.unpaid_fines_total).toFixed(2) }}</div>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
      <button
        @click="activeTab = 'catalog'"
        class="px-4 py-2 text-xs font-semibold rounded-lg transition"
        :class="activeTab === 'catalog'
          ? 'bg-blue-600 text-white shadow-sm'
          : 'text-slate-400 hover:text-white bg-slate-900/60 border border-slate-800'"
      >
        📚 Book Catalog ({{ libraryStore.books.length }})
      </button>

      <button
        v-if="canManage"
        @click="switchTab('loans')"
        class="px-4 py-2 text-xs font-semibold rounded-lg transition"
        :class="activeTab === 'loans'
          ? 'bg-blue-600 text-white shadow-sm'
          : 'text-slate-400 hover:text-white bg-slate-900/60 border border-slate-800'"
      >
        🔄 Circulation Desk
      </button>

      <button
        v-if="canManage"
        @click="switchTab('overdue')"
        class="px-4 py-2 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
        :class="activeTab === 'overdue'
          ? 'bg-rose-600 text-white shadow-sm'
          : 'text-slate-400 hover:text-white bg-slate-900/60 border border-slate-800'"
      >
        <span>⚠️ Overdue Tracker</span>
        <span v-if="libraryStore.summary?.overdue_loans > 0" class="px-1.5 py-0.2 bg-rose-500/20 text-rose-300 rounded text-[10px]">
          {{ libraryStore.summary.overdue_loans }}
        </span>
      </button>

      <button
        v-if="canManage"
        @click="switchTab('fines')"
        class="px-4 py-2 text-xs font-semibold rounded-lg transition"
        :class="activeTab === 'fines'
          ? 'bg-amber-600 text-white shadow-sm'
          : 'text-slate-400 hover:text-white bg-slate-900/60 border border-slate-800'"
      >
        💰 Fines Ledger
      </button>
    </div>

    <!-- TAB 1: BOOK CATALOG -->
    <div v-if="activeTab === 'catalog'" class="space-y-4">
      <!-- Search & Filters Toolbar -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex-1 flex flex-wrap items-center gap-3">
          <input
            v-model="catalogSearch"
            @keyup.enter="applyCatalogFilters"
            type="text"
            placeholder="Search book title, author, ISBN, shelf..."
            class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 w-full sm:w-64 focus:outline-none focus:border-blue-500"
          />

          <select
            v-model="catalogCategory"
            @change="applyCatalogFilters"
            class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500"
          >
            <option value="">All Categories</option>
            <option value="Literature">Literature</option>
            <option value="Fiction">Fiction</option>
            <option value="Science">Science</option>
            <option value="Mathematics">Mathematics</option>
            <option value="History">History</option>
            <option value="Computer Science">Computer Science</option>
            <option value="General">General</option>
          </select>

          <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="catalogAvailableOnly"
              @change="applyCatalogFilters"
              class="rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-0"
            />
            <span>Available only</span>
          </label>
        </div>

        <button
          @click="applyCatalogFilters"
          class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg transition"
        >
          Search Catalog
        </button>
      </div>

      <!-- Books Table -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Title &amp; Author</th>
                <th class="p-3.5">Category</th>
                <th class="p-3.5">ISBN &amp; Shelf</th>
                <th class="p-3.5 text-center">Available / Total</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="libraryStore.loading" class="text-center">
                <td colspan="5" class="p-8 text-slate-500">Loading catalog books...</td>
              </tr>
              <tr v-else-if="libraryStore.books.length === 0" class="text-center">
                <td colspan="5" class="p-8 text-slate-500">No books found matching your criteria.</td>
              </tr>
              <tr v-for="book in libraryStore.books" :key="book.id" class="hover:bg-slate-950/40">
                <td class="p-3.5">
                  <div class="font-bold text-white text-sm">{{ book.title }}</div>
                  <div class="text-[11px] text-slate-400 mt-0.5">by {{ book.author }}</div>
                  <div v-if="book.publisher" class="text-[10px] text-slate-500">
                    {{ book.publisher }} {{ book.publication_year ? `(${book.publication_year})` : '' }}
                  </div>
                </td>
                <td class="p-3.5">
                  <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-medium text-[11px] border border-slate-700">
                    {{ book.category }}
                  </span>
                </td>
                <td class="p-3.5 font-mono text-[11px]">
                  <div class="text-slate-300">{{ book.isbn || 'No ISBN' }}</div>
                  <div class="text-slate-500 text-[10px]">📍 {{ book.shelf_location || 'Shelf TBA' }}</div>
                </td>
                <td class="p-3.5 text-center">
                  <span
                    class="px-2.5 py-1 rounded-full text-xs font-bold font-mono inline-block"
                    :class="book.copies_available > 0
                      ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                      : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                  >
                    {{ book.copies_available }} / {{ book.copies_total }}
                  </span>
                </td>
                <td class="p-3.5 text-right space-x-1.5">
                  <button
                    v-if="canManage && book.copies_available > 0"
                    @click="openCheckoutModal(book)"
                    class="px-2.5 py-1 text-[11px] font-semibold bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition"
                  >
                    Check Out
                  </button>
                  <button
                    v-if="canManage"
                    @click="openEditBookModal(book)"
                    class="px-2 py-1 text-[11px] font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition"
                  >
                    Edit
                  </button>
                  <button
                    v-if="canManage"
                    @click="deleteBook(book)"
                    class="px-2 py-1 text-[11px] font-semibold text-rose-400 hover:bg-rose-500/10 rounded-lg transition"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: CIRCULATION DESK (LOANS) -->
    <div v-if="activeTab === 'loans' && canManage" class="space-y-4">
      <!-- Loan Filters Toolbar -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex-1 flex flex-wrap items-center gap-3">
          <input
            v-model="loanSearch"
            @keyup.enter="applyLoanFilters"
            type="text"
            placeholder="Search borrower or book..."
            class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 w-full sm:w-64 focus:outline-none focus:border-blue-500"
          />

          <select
            v-model="loanStatus"
            @change="applyLoanFilters"
            class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500"
          >
            <option value="">All Loans</option>
            <option value="borrowed">Active / Borrowed</option>
            <option value="overdue">Overdue Only</option>
            <option value="returned">Returned</option>
          </select>
        </div>

        <button
          @click="applyLoanFilters"
          class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg transition"
        >
          Filter Loans
        </button>
      </div>

      <!-- Loans Table -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Book Title</th>
                <th class="p-3.5">Borrower</th>
                <th class="p-3.5">Borrowed Date</th>
                <th class="p-3.5">Due Date</th>
                <th class="p-3.5">Status &amp; Fines</th>
                <th class="p-3.5 text-right">Circulation</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="libraryStore.loading" class="text-center">
                <td colspan="6" class="p-8 text-slate-500">Loading loans...</td>
              </tr>
              <tr v-else-if="libraryStore.loans.length === 0" class="text-center">
                <td colspan="6" class="p-8 text-slate-500">No circulation loan records found.</td>
              </tr>
              <tr v-for="loan in libraryStore.loans" :key="loan.id" class="hover:bg-slate-950/40">
                <td class="p-3.5">
                  <div class="font-bold text-white text-sm">{{ loan.book?.title }}</div>
                  <div class="text-[11px] text-slate-400">{{ loan.book?.author }}</div>
                  <div class="text-[10px] font-mono text-slate-500">ISBN: {{ loan.book?.isbn || '-' }}</div>
                </td>
                <td class="p-3.5">
                  <div class="font-semibold text-white">{{ loan.borrower_name }}</div>
                  <span class="text-[10px] uppercase font-mono px-1.5 py-0.2 rounded bg-slate-800 text-slate-300">
                    {{ loan.borrower_type }} {{ loan.student?.admission_number ? `(${loan.student.admission_number})` : '' }}
                  </span>
                </td>
                <td class="p-3.5 font-mono text-slate-400">
                  {{ formatDate(loan.borrowed_at) }}
                </td>
                <td class="p-3.5 font-mono">
                  <span :class="loan.is_overdue && !loan.returned_at ? 'text-rose-400 font-bold' : 'text-slate-300'">
                    {{ formatDate(loan.due_at) }}
                  </span>
                  <div v-if="loan.is_overdue && !loan.returned_at" class="text-[10px] text-rose-400 font-semibold">
                    {{ loan.days_overdue }} day(s) late
                  </div>
                </td>
                <td class="p-3.5">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                      :class="{
                        'bg-emerald-500/10 text-emerald-400': loan.status === 'returned',
                        'bg-blue-500/10 text-blue-400': loan.status === 'borrowed' && !loan.is_overdue,
                        'bg-rose-500/10 text-rose-400': loan.is_overdue && !loan.returned_at,
                      }"
                    >
                      {{ loan.is_overdue && !loan.returned_at ? 'OVERDUE' : loan.status }}
                    </span>
                    <span v-if="loan.fine_amount > 0" class="text-[11px] font-mono text-amber-400">
                      ${{ Number(loan.fine_amount).toFixed(2) }}
                      <span class="text-[10px]" :class="loan.fine_paid ? 'text-emerald-400' : 'text-rose-400'">
                        ({{ loan.fine_paid ? 'Paid' : 'Unpaid' }})
                      </span>
                    </span>
                  </div>
                </td>
                <td class="p-3.5 text-right">
                  <button
                    v-if="!loan.returned_at"
                    @click="openCheckinModal(loan)"
                    class="px-2.5 py-1 text-[11px] font-semibold bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition"
                  >
                    Check In
                  </button>
                  <span v-else class="text-[11px] text-slate-500 font-mono">
                    Returned {{ formatDate(loan.returned_at) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: OVERDUE TRACKER -->
    <div v-if="activeTab === 'overdue' && canManage" class="space-y-4">
      <div class="bg-rose-500/10 border border-rose-500/20 rounded-xl p-4 flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-rose-300 flex items-center gap-2">
            <span>Active Overdue Books</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-200 font-mono">
              Action Required
            </span>
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">
            Borrowers with past-due items. Returning an overdue item automatically calculates fine at $0.50/day into the ledger.
          </p>
        </div>
      </div>

      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Book Title</th>
                <th class="p-3.5">Borrower</th>
                <th class="p-3.5">Due Date</th>
                <th class="p-3.5 text-center">Days Late</th>
                <th class="p-3.5">Accrued Fine</th>
                <th class="p-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="overdueLoansList.length === 0" class="text-center">
                <td colspan="6" class="p-8 text-slate-500">🎉 No active overdue books! All items are within return window.</td>
              </tr>
              <tr v-for="loan in overdueLoansList" :key="loan.id" class="hover:bg-slate-950/40">
                <td class="p-3.5 font-bold text-white">{{ loan.book?.title }}</td>
                <td class="p-3.5">
                  <div class="font-semibold text-white">{{ loan.borrower_name }}</div>
                  <div class="text-[10px] text-slate-500">{{ loan.student?.user?.email || loan.staff?.user?.email }}</div>
                </td>
                <td class="p-3.5 font-mono text-rose-400 font-semibold">{{ formatDate(loan.due_at) }}</td>
                <td class="p-3.5 text-center font-bold text-rose-400 font-mono">{{ loan.days_overdue }} days</td>
                <td class="p-3.5 font-mono text-amber-400 font-bold">
                  ${{ (loan.days_overdue * 0.50).toFixed(2) }}
                </td>
                <td class="p-3.5 text-right">
                  <button
                    @click="openCheckinModal(loan)"
                    class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg transition"
                  >
                    Check In Now
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 4: FINES LEDGER -->
    <div v-if="activeTab === 'fines' && canManage" class="space-y-4">
      <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <select
            v-model="fineStatus"
            @change="applyFineFilters"
            class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500"
          >
            <option value="">All Fines</option>
            <option value="unpaid">Unpaid Only</option>
            <option value="paid">Paid</option>
            <option value="waived">Waived</option>
          </select>
        </div>
      </div>

      <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-400 font-medium border-b border-slate-800">
              <tr>
                <th class="p-3.5">Date</th>
                <th class="p-3.5">Borrower</th>
                <th class="p-3.5">Book Title</th>
                <th class="p-3.5">Reason / Notes</th>
                <th class="p-3.5 text-right">Amount</th>
                <th class="p-3.5 text-center">Status</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="libraryStore.fines.length === 0" class="text-center">
                <td colspan="7" class="p-8 text-slate-500">No fine records found.</td>
              </tr>
              <tr v-for="fine in libraryStore.fines" :key="fine.id" class="hover:bg-slate-950/40">
                <td class="p-3.5 font-mono text-slate-400">{{ formatDate(fine.created_at) }}</td>
                <td class="p-3.5 font-semibold text-white">
                  {{ fine.student?.user?.name || fine.staff?.user?.name || fine.user?.name || 'Borrower' }}
                </td>
                <td class="p-3.5 text-white">{{ fine.loan?.book?.title || 'Book Loan' }}</td>
                <td class="p-3.5 text-slate-400 text-[11px]">{{ fine.notes || fine.type }}</td>
                <td class="p-3.5 text-right font-mono font-bold text-amber-400">
                  ${{ Number(fine.amount).toFixed(2) }}
                </td>
                <td class="p-3.5 text-center">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                    :class="{
                      'bg-rose-500/10 text-rose-400': fine.status === 'unpaid',
                      'bg-emerald-500/10 text-emerald-400': fine.status === 'paid',
                      'bg-slate-800 text-slate-400': fine.status === 'waived',
                    }"
                  >
                    {{ fine.status }}
                  </span>
                </td>
                <td class="p-3.5 text-right space-x-1.5">
                  <button
                    v-if="fine.status === 'unpaid'"
                    @click="openPayFineModal(fine)"
                    class="px-2.5 py-1 text-[11px] font-semibold bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition"
                  >
                    Record Payment
                  </button>
                  <button
                    v-if="fine.status === 'unpaid'"
                    @click="openWaiveFineModal(fine)"
                    class="px-2 py-1 text-[11px] font-semibold bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-lg transition"
                  >
                    Waive
                  </button>
                  <span v-else class="text-[10px] text-slate-500 font-mono">
                    {{ fine.payment_method ? `Via ${fine.payment_method}` : '-' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL 1: CHECKOUT BOOK -->
    <div v-if="showCheckoutModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Check Out Book</span>
          <button @click="showCheckoutModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <form @submit.prevent="submitCheckout" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Select Book</label>
            <select
              v-model="checkoutForm.book_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            >
              <option value="">-- Choose Book from Catalog --</option>
              <option
                v-for="b in availableBooks"
                :key="b.id"
                :value="b.id"
              >
                {{ b.title }} ({{ b.copies_available }} available)
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Borrower (Student)</label>
            <select
              v-model="checkoutForm.student_id"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            >
              <option value="">-- Choose Student --</option>
              <option v-for="stu in studentsList" :key="stu.id" :value="stu.id">
                {{ stu.user?.name }} ({{ stu.admission_number }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Due Date</label>
            <input
              v-model="checkoutForm.due_at"
              type="date"
              required
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            />
            <span class="text-[10px] text-slate-500 mt-0.5 inline-block">Defaults to 14 days standard loan period.</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Remarks / Notes</label>
            <input
              v-model="checkoutForm.notes"
              type="text"
              placeholder="e.g. English literature course reading"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showCheckoutModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="libraryStore.actionLoading"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ libraryStore.actionLoading ? 'Issuing Loan...' : 'Confirm Check Out' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: CHECKIN BOOK & FINES CALCULATION -->
    <div v-if="showCheckinModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Return Book (Check In)</span>
          <button @click="showCheckinModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 space-y-1">
          <div class="text-xs text-slate-400">Returning Book:</div>
          <div class="text-sm font-bold text-white">{{ selectedLoanForCheckin?.book?.title }}</div>
          <div class="text-xs text-slate-400">Borrower: <span class="text-slate-200">{{ selectedLoanForCheckin?.borrower_name }}</span></div>
          <div class="text-xs text-slate-400">Due Date: <span class="text-slate-200 font-mono">{{ formatDate(selectedLoanForCheckin?.due_at) }}</span></div>
        </div>

        <!-- Overdue Notice & Fine Calculation -->
        <div v-if="selectedLoanForCheckin?.is_overdue" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 space-y-1">
          <div class="flex items-center justify-between text-xs">
            <span class="font-bold text-rose-400">⚠️ Book is Overdue:</span>
            <span class="font-bold text-rose-300 font-mono">{{ selectedLoanForCheckin.days_overdue }} days</span>
          </div>
          <div class="flex items-center justify-between text-xs">
            <span class="text-slate-400">Overdue Rate:</span>
            <span class="text-slate-300 font-mono">$0.50 / day</span>
          </div>
          <div class="flex items-center justify-between text-xs pt-1 border-t border-rose-500/20 font-bold">
            <span class="text-white">Calculated Overdue Fine:</span>
            <span class="text-amber-400 font-mono">${{ (selectedLoanForCheckin.days_overdue * 0.50).toFixed(2) }}</span>
          </div>
        </div>
        <div v-else class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 flex items-center gap-2">
          <span>✓</span>
          <span>Book returned within due date. Zero overdue fines will be incurred.</span>
        </div>

        <form @submit.prevent="submitCheckin" class="space-y-3">
          <div v-if="selectedLoanForCheckin?.is_overdue" class="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="fine_paid"
              v-model="checkinForm.fine_paid"
              class="rounded bg-slate-950 border-slate-800 text-emerald-600 focus:ring-0"
            />
            <label for="fine_paid" class="text-xs text-slate-300 cursor-pointer">
              Borrower is paying the fine now at the circulation counter
            </label>
          </div>

          <div v-if="checkinForm.fine_paid">
            <label class="block text-xs font-semibold text-slate-300 mb-1">Payment Method</label>
            <select
              v-model="checkinForm.payment_method"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            >
              <option value="cash">Cash</option>
              <option value="card">Debit / Credit Card</option>
              <option value="online">Online / Student Account</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Check-in Remarks</label>
            <input
              v-model="checkinForm.notes"
              type="text"
              placeholder="e.g. Good condition"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showCheckinModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="libraryStore.actionLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ libraryStore.actionLoading ? 'Checking in...' : 'Confirm Return' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: ADD OR EDIT BOOK TITLE -->
    <div v-if="showBookModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>{{ isEditingBook ? 'Edit Book in Catalog' : 'Add New Book to Catalog' }}</span>
          <button @click="showBookModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <form @submit.prevent="submitBookForm" class="space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Book Title</label>
              <input
                v-model="bookForm.title"
                type="text"
                required
                placeholder="e.g. The Hobbit"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Author</label>
              <input
                v-model="bookForm.author"
                type="text"
                required
                placeholder="e.g. J.R.R. Tolkien"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Category</label>
              <select
                v-model="bookForm.category"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              >
                <option value="Literature">Literature</option>
                <option value="Fiction">Fiction</option>
                <option value="Science">Science</option>
                <option value="Mathematics">Mathematics</option>
                <option value="History">History</option>
                <option value="Computer Science">Computer Science</option>
                <option value="General">General</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">ISBN</label>
              <input
                v-model="bookForm.isbn"
                type="text"
                placeholder="9780261102217"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Total Copies</label>
              <input
                v-model.number="bookForm.copies_total"
                type="number"
                min="1"
                required
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Shelf Location / Code</label>
              <input
                v-model="bookForm.shelf_location"
                type="text"
                placeholder="e.g. FIC-TOL-01"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Publisher</label>
              <input
                v-model="bookForm.publisher"
                type="text"
                placeholder="e.g. Allen & Unwin"
                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Description</label>
            <textarea
              v-model="bookForm.description"
              rows="2"
              placeholder="Brief description or synopsis..."
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showBookModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="libraryStore.actionLoading"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-lg shadow-sm"
            >
              {{ libraryStore.actionLoading ? 'Saving...' : (isEditingBook ? 'Update Book' : 'Add Book') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 4: PAY FINE -->
    <div v-if="showPayModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center justify-between">
          <span>Record Fine Payment</span>
          <button @click="showPayModal = false" class="text-slate-500 hover:text-white">✕</button>
        </h3>

        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
          <div class="text-xs text-slate-400">Total Fine Due:</div>
          <div class="text-xl font-bold font-mono text-amber-400">${{ Number(selectedFine?.amount || 0).toFixed(2) }}</div>
        </div>

        <form @submit.prevent="submitPayFine" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Payment Method</label>
            <select
              v-model="payFineForm.payment_method"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            >
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="online">Online</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Payment Receipt / Note</label>
            <input
              v-model="payFineForm.notes"
              type="text"
              placeholder="e.g. Counter receipt #1042"
              class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              @click="showPayModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="libraryStore.actionLoading"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white rounded-lg"
            >
              {{ libraryStore.actionLoading ? 'Saving...' : 'Record Payment' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useLibraryStore } from '../stores/library';
import axios from 'axios';

const authStore = useAuthStore();
const libraryStore = useLibraryStore();

const activeTab = ref('catalog');
const catalogSearch = ref('');
const catalogCategory = ref('');
const catalogAvailableOnly = ref(false);

const loanSearch = ref('');
const loanStatus = ref('');
const fineStatus = ref('');

const studentsList = ref([]);

const showCheckoutModal = ref(false);
const showCheckinModal = ref(false);
const showBookModal = ref(false);
const showPayModal = ref(false);

const isEditingBook = ref(false);
const selectedBook = ref(null);
const selectedLoanForCheckin = ref(null);
const selectedFine = ref(null);

const checkoutForm = ref({
    book_id: '',
    student_id: '',
    due_at: '',
    notes: '',
});

const checkinForm = ref({
    fine_paid: false,
    payment_method: 'cash',
    notes: '',
});

const bookForm = ref({
    title: '',
    author: '',
    isbn: '',
    category: 'Literature',
    copies_total: 1,
    shelf_location: '',
    publisher: '',
    description: '',
});

const payFineForm = ref({
    payment_method: 'cash',
    notes: '',
});

const canManage = computed(() => {
    return authStore.isSchoolAdmin || authStore.isSuperAdmin || authStore.user?.role === 'librarian';
});

const availableBooks = computed(() => {
    return libraryStore.books.filter(b => b.copies_available > 0);
});

const overdueLoansList = computed(() => {
    return libraryStore.loans.filter(l => l.is_overdue && !l.returned_at);
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function switchTab(tab) {
    activeTab.value = tab;
    if (tab === 'loans' || tab === 'overdue') {
        libraryStore.fetchLoans();
    } else if (tab === 'fines') {
        libraryStore.fetchFines();
    }
}

async function applyCatalogFilters() {
    await libraryStore.fetchBooks({
        search: catalogSearch.value || undefined,
        category: catalogCategory.value || undefined,
        available_only: catalogAvailableOnly.value ? true : undefined,
    });
}

async function applyLoanFilters() {
    await libraryStore.fetchLoans({
        search: loanSearch.value || undefined,
        status: loanStatus.value || undefined,
    });
}

async function applyFineFilters() {
    await libraryStore.fetchFines({
        status: fineStatus.value || undefined,
    });
}

async function loadStudents() {
    try {
        const res = await axios.get('/students?all=true');
        studentsList.value = res.data.data || [];
    } catch (e) {
        studentsList.value = [];
    }
}

function openCheckoutModal(book = null) {
    const twoWeeksLater = new Date();
    twoWeeksLater.setDate(twoWeeksLater.getDate() + 14);

    checkoutForm.value = {
        book_id: book ? book.id : '',
        student_id: '',
        due_at: twoWeeksLater.toISOString().split('T')[0],
        notes: '',
    };
    showCheckoutModal.value = true;
    loadStudents();
}

async function submitCheckout() {
    try {
        await libraryStore.checkoutBook(checkoutForm.value);
        showCheckoutModal.value = false;
        if (activeTab.value === 'loans') {
            await libraryStore.fetchLoans();
        }
    } catch (e) {
        // handled in store
    }
}

function openCheckinModal(loan) {
    selectedLoanForCheckin.value = loan;
    checkinForm.value = {
        fine_paid: false,
        payment_method: 'cash',
        notes: '',
    };
    showCheckinModal.value = true;
}

async function submitCheckin() {
    if (!selectedLoanForCheckin.value) return;
    try {
        await libraryStore.checkinBook(selectedLoanForCheckin.value.id, checkinForm.value);
        showCheckinModal.value = false;
        await libraryStore.fetchLoans();
    } catch (e) {
        // handled in store
    }
}

function openAddBookModal() {
    isEditingBook.value = false;
    selectedBook.value = null;
    bookForm.value = {
        title: '',
        author: '',
        isbn: '',
        category: 'Literature',
        copies_total: 1,
        shelf_location: '',
        publisher: '',
        description: '',
    };
    showBookModal.value = true;
}

function openEditBookModal(book) {
    isEditingBook.value = true;
    selectedBook.value = book;
    bookForm.value = {
        title: book.title,
        author: book.author,
        isbn: book.isbn || '',
        category: book.category,
        copies_total: book.copies_total,
        shelf_location: book.shelf_location || '',
        publisher: book.publisher || '',
        description: book.description || '',
    };
    showBookModal.value = true;
}

async function submitBookForm() {
    try {
        if (isEditingBook.value && selectedBook.value) {
            await libraryStore.updateBook(selectedBook.value.id, bookForm.value);
        } else {
            await libraryStore.createBook(bookForm.value);
        }
        showBookModal.value = false;
    } catch (e) {
        // handled in store
    }
}

async function deleteBook(book) {
    if (!confirm(`Are you sure you want to delete '${book.title}' from the catalog?`)) return;
    try {
        await libraryStore.deleteBook(book.id);
    } catch (e) {
        // handled in store
    }
}

function openPayFineModal(fine) {
    selectedFine.value = fine;
    payFineForm.value = {
        payment_method: 'cash',
        notes: '',
    };
    showPayModal.value = true;
}

async function submitPayFine() {
    if (!selectedFine.value) return;
    try {
        await libraryStore.payFine(selectedFine.value.id, payFineForm.value);
        showPayModal.value = false;
    } catch (e) {
        // handled in store
    }
}

async function openWaiveFineModal(fine) {
    const reason = prompt('Please enter the reason for waiving this library fine:');
    if (!reason) return;
    try {
        await libraryStore.waiveFine(fine.id, reason);
    } catch (e) {
        // handled in store
    }
}

onMounted(async () => {
    await libraryStore.fetchBooks();
    if (canManage.value) {
        await libraryStore.fetchSummary();
    }
});
</script>
