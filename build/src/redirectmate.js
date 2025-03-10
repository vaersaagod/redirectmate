import { createApp } from '../node_modules/vue/dist/vue.esm-bundler.js';
import axios from "axios";
import { stringify } from 'qs';

import LogIndexView from './components/LogIndexView.vue';
import RedirectsIndexView from './components/RedirectsIndexView.vue';
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
    inject: ['$axios', 'Craft', 'Garnish'],
    data() {
        return {
            activeTab: window.LOCATION_HASH || 'tab-log',
        }
    },
    methods: {
        initTabs() {
            const tabs = document.getElementById('redirectmate-tabs');
            if (!tabs) {
                return;
            }
            const tabManager = new Craft.Tabs(tabs);
            tabManager.on('selectTab', e => {
                this.activeTab = e.$tab.data('id');
                Garnish.requestAnimationFrame(() => {
                    history.replaceState(undefined, undefined, e.$tab.attr('href'));
                });
            });
            tabManager.selectTab(this.activeTab);
        }
    },
    mounted() {
        this.initTabs();
    },
    compilerOptions: {
        delimiters: ["${", "}$"]
    }
});

app.component('LogIndexView', LogIndexView);
app.component('RedirectsIndexView', RedirectsIndexView);
app.component('CreateRedirectModal', CreateRedirectModal);

app.provide('$axios', axiosInstance);
app.provide('Craft', window.Craft);
app.provide('Garnish', window.Garnish);
app.mount('#redirectMateApp');
