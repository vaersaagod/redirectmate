<script>
import isbot from 'isbot';
import UAParser from 'ua-parser-js';

import IndexViewFooter from './includes/IndexViewFooter.vue';

const PER_PAGE_STORAGE_KEY = 'redirectmate:logs:perPage';

export default {
    components: {IndexViewFooter},
    inject: ['$axios', 'Craft'],
    props: {},
    data() {
        return {
            activeModal: false,
            modalMode: 'batch',
            modalEditId: null,

            items: [],
            selectedItems: [],
            checkingItems: [],
            newlyHandledItems: [],
            totalCount: null,

            sites: [],
            settings: [],

            serverParams: {
                page: 1,
                perPage: parseInt(localStorage.getItem(PER_PAGE_STORAGE_KEY) || 50, 10),
                handled: 'all',
                site: 'all',
                sortBy: 'hits',
                search: null
            },
            actions: {

            }
        }
    },

    computed: {
        selectAll: {
            get: function() {
                return this.items ? this.selectedItems.length == this.items.length : false;
            },
            set: function(value) {
                var selected = [];

                if (value) {
                    this.items.forEach(function(item) {
                        selected.push(item.id);
                    });
                }

                this.selectedItems = selected;
            }
        },
        searchQuery: {
            get() {
                return this.serverParams.search;
            },
            set(value) {
                // Make sure the page is reset to 1 when the search query changes
                this.serverParams.page = 1;
                this.serverParams.search = value;
            }
        }
    },

    watch: {
        serverParams: {
            // Update table whenever the server params change
            handler() {
                localStorage.setItem(PER_PAGE_STORAGE_KEY, this.serverParams.perPage);
                this.updateTable();
            },
            deep: true
        },
        items() {
            // Scroll to top when the list of items change, and deselect any selected items
            this.selectedItems = [];
            window.scrollTo(0, 0);
        }
    },

    methods: {
        loadItems() {
            this.$axios.post(this.actions.getLogs, this.serverParams)
                .then(({ data }) => {
                    this.totalCount = parseInt(data.count, 10);
                    this.items = data.data;
                    console.log(data, this.totalCount);
                })
                .catch(error => {
                    console.error(error);
                });
        },
        updateTable() {
            this.loadItems();
        },
        resolve() {
            this.modalMode = 'batch';
            this.modalEditId = null;
            this.activeModal = true;
        },
        clearLog() {
            if (window.confirm(Craft.t('redirectmate', 'Are you sure you want to clear the log?'))) {
                this.$axios.post(this.actions.deleteAllLogItems, {})
                    .then(({ data }) => {
                        this.loadItems();
                    })
                    .catch(error => {
                        console.error(error);
                    });
            }
        },
        closeModal() {
            this.activeModal = false;
            this.loadItems();
        },
        batchCheckItems() {
            console.log('batchCheckItems', this.selectedItems);
            this.checkingItems = this.selectedItems;
            this.selectedItems = [];

            this.checkNextItem();
        },
        checkNextItem() {
            const nextId = this.checkingItems[0];

            this.$axios.post(this.actions.checkLogItem, { id: nextId })
                .then(({ data }) => {
                    console.log(data);

                    this.updateHandledStatus(nextId, data.handled);
                    this.checkingItems.shift()

                    if (this.checkingItems.length > 0) {
                        this.checkNextItem();
                    } else {
                        // what to do now?
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        },
        updateHandledStatus(id, handled) {
            for (let i = 0; i < this.items.length; i++) {
                const item = this.items[i];
                if (item.id === id) {
                    item.handled = handled;
                    break;
                }
            }
        },
        batchDeleteItems() {
            console.log('batchDeleteItems', this.selectedItems);

            this.$axios.post(this.actions.deleteLogItems, { ids: this.selectedItems })
                .then(({ data }) => {
                    console.log(data);
                    this.selectedItems = [];
                    this.loadItems();
                })
                .catch(error => {
                    console.error(error);
                });
        },
        addRedirectForId(id) {
            this.modalMode = 'single';
            this.modalEditId = id;
            this.activeModal = true;
        },
        isUaBot(ua) {
            return isbot(ua);
        },
        getBot(ua) {
            return isbot.find(ua);
        },
        getBrowser(ua) {
            if (ua === null) { return '' }
            const uaParsed = new UAParser(ua);
            return `${uaParsed.getBrowser().name} ${uaParsed.getBrowser().major} / ${uaParsed.getOS().name} ${uaParsed.getOS().version}`;
        },
        getItemSourceUrl(item) {
            const site = this.sites.find( ({ id }) => id === parseInt(item.siteId, 10));
            return this.Craft.getUrl(item.sourceUrl.substring(1), null, site.baseUrl);
        },
        formatDateTime(dateTime) {
            if (!dateTime) {
                return '';
            }
            const date = new Date(dateTime);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        trackingEnabled(prop) {
            return this.settings.track ? this.settings.track.includes(prop) : false;
        }
    },

    mounted() {
        console.log('mounted');

        const { sites, actions, settings } = window.redirectMate;

        if (sites && sites.length) {
            this.sites = sites;
        }

        this.actions = actions;
        this.settings = settings;

        this.loadItems();
        Craft.initUiElements();
    }
}
</script>

<template>
    <div class="flex flex-col">
        <div class="flex w-100 justify-between">
            <div id="toolbar" class="flex">

                <div class="select" v-if="sites && sites.length > 1">
                    <select v-model="serverParams.site" @change="updateTable">
                        <option value="all">{{ Craft.t('redirectmate', 'All sites' )}}</option>
                        <option v-for="item in sites" :value="item.id">{{ item.name }}</option>
                    </select>
                </div>

                <div class="select">
                    <select v-model="serverParams.handled" @change="updateTable">
                        <option value="all">{{ Craft.t('redirectmate', 'All errors') }}</option>
                        <option value="handled">{{ Craft.t('redirectmate', 'Handled') }}</option>
                        <option value="nothandled">{{ Craft.t('redirectmate', 'Not Handled') }}</option>
                    </select>
                </div>

                <div class="select">
                    <select v-model="serverParams.sortBy" @change="updateTable">
                        <option value="hits">{{ Craft.t('redirectmate', 'By hits') }}</option>
                        <option value="lasthit">{{ Craft.t('redirectmate', 'Last hit') }}</option>
                        <option value="newest">{{ Craft.t('redirectmate', 'Created') }}</option>
                    </select>
                </div>

                <!-- Actions menu -->
                <div :class="{ hidden: selectedItems.length === 0 }">
                    <div data-icon="settings" class="btn menubtn"></div>
                    <div data-align="left" class="menu">
                        <ul role="listbox">
                            <li @click="batchDeleteItems"><a>{{ Craft.t('redirectmate', 'Delete') }}</a></li>
                            <li><a @click="batchCheckItems">{{ Craft.t('redirectmate', 'Check') }}</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Search -->
                <div class="flex-grow texticon search icon">
                    <input type="text" class="clearable text fullwidth" autocomplete="off" placeholder="Search" dir="ltr" aria-label="Search" v-model="searchQuery">
                    <button class="clear-btn" :class="{ hidden: !serverParams.search }" title="Clear search" role="button" aria-label="Clear search" @click="serverParams.search = ''"></button>
                </div>

            </div>

            <div class="flex">
                <button @click="clearLog" class="btn delete icon" :class="{ disabled: !totalCount }" :disabled="!totalCount">{{ Craft.t('redirectmate', 'Clear log') }}</button>
                <button @click="resolve" class="btn submit add icon" :class="{ disabled: !totalCount }">{{ Craft.t('redirectmate', 'Resolve') }}</button>
            </div>
        </div>

        <div class="flex" v-if="items == null">
            <span class="spinner mx-auto py-70"></span>
        </div>

        <div class="w-100" v-if="items && items.length == 0">
            <p class="mx-auto py-70 text-center">{{ Craft.t('redirectmate', 'No errors have been logged.') }}</p>
        </div>

        <div class="w-100">
            <div class="tableview tablepane mt-20" v-if="items && items.length > 0">
              <table class="data fullwidth w-100">
                  <thead>
                      <tr>
                          <th class="checkbox-column"><input type="checkbox" v-model="selectAll" class="relative top-2px"></th>
                          <th>{{ Craft.t('redirectmate', 'Source URL') }}</th>
                          <th>&nbsp;</th>
                          <th>
                              <div class="text-center">{{ Craft.t('redirectmate', 'Hits') }}</div>
                          </th>
                          <th v-if="trackingEnabled('ip')">{{ Craft.t('redirectmate', 'Remote IP') }}</th>
                          <th v-if="trackingEnabled('useragent')">{{ Craft.t('redirectmate', 'User Agent') }}</th>
                          <th v-if="trackingEnabled('referrer')">{{ Craft.t('redirectmate', 'Referrer') }}</th>
                      </tr>
                  </thead>

                  <tbody>
                      <tr class="group" v-for="logItem in items" :key="'logItem' + logItem.id">
                          <td>
                              <input type="checkbox" v-model="selectedItems" :value="logItem.id" class="relative top-2px">
                          </td>
                          <td class="break-all" width="40%">
                              <span class="status-dot inline-block w-10px h-10px rounded-100 mr-10" :class="{ 'bg-green-600': logItem.handled || newlyHandledItems.includes(logItem.id), 'bg-red-600': !logItem.handled && !newlyHandledItems.includes(logItem.id), 'is-checking': checkingItems.includes(logItem.id) }" :title="logItem.handled ? Craft.t('redirectmate', 'Handled') : Craft.t('redirectmate', 'Not handled')"></span>
                              <a :href="getItemSourceUrl(logItem)" class="go" target="_blank">{{ logItem.sourceUrl }}</a>
                          </td>
                          <td>
                              <div class="text-right">
                                  <button v-if="!logItem.handled" @click="addRedirectForId(logItem.id)" class="btn small">{{ Craft.t('redirectmate', 'Fix') }}</button>
                              </div>
                          </td>
                          <td :title="`${Craft.t('redirectmate', 'Last hit')}: ${formatDateTime(logItem.lastHit)}\n${Craft.t('redirectmate', 'Created')}: ${formatDateTime(logItem.dateCreated)}`">
                              <div class="text-center">
                                  {{ logItem.hits }}
                              </div>
                          </td>
                          <td v-if="trackingEnabled('ip')">
                              <span v-if="logItem.remoteIp === null || logItem.remoteIp === '127.0.0.1'" class="inline-block">{{ logItem.remoteIp }}</span>
                              <a v-else class="inline-block go" :href="'https://whatismyipaddress.com/ip/' + logItem.remoteIp" target="_blank">{{ logItem.remoteIp }}</a>
                          </td>
                          <td :title="logItem.userAgent"  v-if="trackingEnabled('useragent')">
                              <span class="capitalize" v-if="isUaBot(logItem.userAgent)">{{ getBot(logItem.userAgent) }}</span>
                              <span v-else>{{ getBrowser(logItem.userAgent) }}</span>

                          </td>
                          <td v-if="trackingEnabled('referrer')">
                              <a :href="logItem.referrer" v-if="logItem.referrer != null" class="inline-flex go gap-0"><span class="inline-block max-w-[180px] truncate" :title="logItem.referrer">{{ logItem.referrer }}</span></a>
                          </td>
                      </tr>
                  </tbody>
              </table>
            </div>

            <IndexViewFooter
                :current-page="serverParams.page"
                :per-page="serverParams.perPage"
                :total-count="totalCount"
                :export-action="actions.exportLogs"
                @per-page-change="perPage => this.serverParams.perPage = parseInt(perPage, 10)"
                @current-page-change="currentPage => this.serverParams.page = parseInt(currentPage, 10)"
            />

        </div>

        <create-redirect-modal
            :is-visible="activeModal"
            :close-callback="closeModal"
            :log-items="items"
            :mode="modalMode"
            :edit-id="modalEditId"
            :parent-selected-site="serverParams.site"
            :sites="sites"
        />
    </div>
</template>
