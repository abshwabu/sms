import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import SchoolsView from '../views/SchoolsView.vue';
import CoursesView from '../views/CoursesView.vue';
import HealthView from '../views/HealthView.vue';
import AuthView from '../views/AuthView.vue';
import OnboardingView from '../views/OnboardingView.vue';
import AcademicView from '../views/AcademicView.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
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
