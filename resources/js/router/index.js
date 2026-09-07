import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import SchoolsView from '../views/SchoolsView.vue';
import CoursesView from '../views/CoursesView.vue';
import HealthView from '../views/HealthView.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
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
