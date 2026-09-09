import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import SchoolsView from '../views/SchoolsView.vue';
import CoursesView from '../views/CoursesView.vue';
import HealthView from '../views/HealthView.vue';
import AuthView from '../views/AuthView.vue';
import OnboardingView from '../views/OnboardingView.vue';
import AcademicView from '../views/AcademicView.vue';
import StudentsView from '../views/StudentsView.vue';
import StaffView from '../views/StaffView.vue';
import ParentPortalView from '../views/ParentPortalView.vue';
import AttendanceView from '../views/AttendanceView.vue';
import GradingView from '../views/GradingView.vue';
import TimetableView from '../views/TimetableView.vue';
import LibraryView from '../views/LibraryView.vue';
import TransportView from '../views/TransportView.vue';
import CommunicationsView from '../views/CommunicationsView.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
    },
    {
        path: '/login',
        name: 'login',
        component: LoginView,
    },
    {
        path: '/register',
        name: 'register',
        component: RegisterView,
    },
    {
        path: '/communications',
        name: 'communications',
        component: CommunicationsView,
    },
    {
        path: '/transport',
        name: 'transport',
        component: TransportView,
    },
    {
        path: '/library',
        name: 'library',
        component: LibraryView,
    },
    {
        path: '/timetable',
        name: 'timetable',
        component: TimetableView,
    },
    {
        path: '/grading',
        name: 'grading',
        component: GradingView,
    },
    {
        path: '/attendance',
        name: 'attendance',
        component: AttendanceView,
    },
    {
        path: '/students',
        name: 'students',
        component: StudentsView,
    },
    {
        path: '/parents',
        name: 'parents',
        component: ParentPortalView,
    },
    {
        path: '/staff',
        name: 'staff',
        component: StaffView,
    },
    {
        path: '/academic',
        name: 'academic',
        component: AcademicView,
    },
    {
        path: '/auth',
        name: 'auth',
        component: AuthView,
    },
    {
        path: '/onboarding',
        name: 'onboarding',
        component: OnboardingView,
    },
    {
        path: '/schools',
        name: 'schools',
        component: SchoolsView,
    },
    {
        path: '/courses',
        name: 'courses',
        component: CoursesView,
    },
    {
        path: '/health',
        name: 'health',
        component: HealthView,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: 'text-indigo-400 bg-slate-800',
});

// The only open URLs are landing page ('/'), login ('/login'), and registration ('/register')
const publicRoutes = ['/', '/login', '/register'];

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');
    const user = localStorage.getItem('auth_user');
    const isAuthenticated = !!token && !!user;

    // Restrict all other routes to authenticated users only
    if (!isAuthenticated && !publicRoutes.includes(to.path)) {
        return next({
            path: '/login',
            query: { redirect: to.fullPath },
        });
    }

    if (isAuthenticated) {
        const parsedUser = JSON.parse(user || '{}');
        const role = parsedUser.role;

        // Redirect already authenticated users away from login and register to dashboard
        if (to.path === '/login' || to.path === '/register') {
            return next({ path: '/' });
        }

        // Strict role guards: prevent non-super-admins from accessing cross-tenant schools management
        if (to.path === '/schools' && role !== 'super_admin') {
            return next({ path: '/' });
        }

        // Platform diagnostics are for super-admin only
        if (to.path === '/health' && role !== 'super_admin') {
            return next({ path: '/' });
        }

        // Onboarding and staff management are for school administrators
        if ((to.path === '/onboarding' || to.path === '/staff' || to.path === '/academic') && 
            role !== 'school_admin' && role !== 'super_admin') {
            return next({ path: '/' });
        }
    }

    next();
});

export default router;
