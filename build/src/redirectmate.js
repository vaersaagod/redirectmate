import { createApp } from '../node_modules/vue/dist/vue.esm-bundler.js';
import axios from "axios";
import { stringify } from 'qs';

import LogView from './components/LogView.vue';
import RedirectsView from './components/RedirectsView.vue';
import CreateRedirectModal from './components/CreateRedirectModal.vue';

import './redirectmate.scss';

if (process.env.NODE_ENV === "development") {
    globalThis.__VUE_OPTIONS_API__ = true
    globalThis.__VUE_PROD_DEVTOOLS__ = true;
} else {
    globalThis.__VUE_OPTIONS_API__ = true;
    globalThis.__VUE_PROD_DEVTOOLS__ = false;
}

const axiosInstance = axios.create({
    headers: {
        'X-CSRF-Token': window.redirectMate.csrfToken
    },
    transformRequest: [
        function(data) {
            return stringify(data)
        },
    ],
});

const app = createApp({
    inject: ['$axios', 'Craft'],
    data() {
        return {
            activeTab: 'log',
        }
    },
    watch: {
        activeTab(newTab, oldTab) {

        }
    },
    methods: {

    },
    mounted() {
    },
    compilerOptions: {
        delimiters: ["${", "}$"]
    }
});

app.component('LogView', LogView);
app.component('RedirectsView', RedirectsView);
app.component('CreateRedirectModal', CreateRedirectModal);

app.provide('$axios', axiosInstance);
app.provide('Craft', window.Craft);
app.mount('#redirectMateApp');
