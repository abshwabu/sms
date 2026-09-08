import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
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

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
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

export default router;
