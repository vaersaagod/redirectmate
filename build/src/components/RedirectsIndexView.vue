<script>
import IndexViewFooter from './includes/IndexViewFooter.vue';

const PER_PAGE_STORAGE_KEY = 'redirectmate:redirects:perPage';

export default {
    components: {IndexViewFooter},
    inject: ['$axios', 'Craft'],
    props: {},
    data() {
        return {
            activeModal: false,
            modalMode: 'single',
            modalEditId: null,

            items: null,
            selectedItems: [],
            totalCount: null,

            sites: [],

            serverParams: {
                page: 1,
                perPage: parseInt(localStorage.getItem(PER_PAGE_STORAGE_KEY) || 50, 10),
                handled: 'all',
                site: 'all',
                sortBy: 'newest',
                search: null
            },

            actions: {}
        };
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
                this.totalCount = 0;
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
            // Deselect any selected items when the list of items is changed
            this.selectedItems = [];
        }
    },

    methods: {
        loadItems() {
            this.$axios.post(this.actions.getRedirects, this.serverParams)
                .then(({ data }) => {
                    console.log(data);
                    this.totalCount = parseInt(data.count, 10);
                    this.items = data.data;
                })
                .catch(error => {
                    console.error(error);
                });
        },
        updateTable() {
            this.loadItems();
        },
        addRedirect() {
            this.modalMode = 'single';
            this.modalEditId = null;
            this.activeModal = true;
        },
        closeModal() {
            this.activeModal = false;
            this.modalEditId = null;
            this.loadItems();
        },
        batchDeleteItems() {
            console.log('batchDeleteItems', this.selectedItems);

            this.$axios.post(this.actions.deleteRedirects, { ids: this.selectedItems })
                .then(({ data }) => {
                    console.log(data);
                    this.selectedItems = [];
                    this.loadItems();
                })
                .catch(error => {
                    console.error(error);
                });

        },
        editRedirect(id) {
            this.modalMode = 'edit';
            this.modalEditId = id;
            this.activeModal = true;
        },
        getSiteName(siteId) {
            if (siteId) {
                const site = this.sites.find( ({ id }) => id === siteId );
                return site ? site.name : Craft.t('redirectmate', 'Unknown');
            } else {
                return Craft.t('redirectmate', 'All sites');
            }
        },
        getItemUrl(item, targetUrl) {
            if (targetUrl.startsWith('http')) {
                return targetUrl;
            }

            // FIXME: Get primary site instead
            let site = this.sites[0];

            if (item.siteId !== null) {
                site = this.sites.find( ({ id }) => id === parseInt(item.siteId, 10));
            }

            return this.Craft.getUrl(targetUrl.substring(1), null, site.baseUrl);
        },
        formatDateTime(dateTime) {
            if (!dateTime) {
                return '';
            }
            const date = new Date(dateTime);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

    },

    mounted() {
        console.log('mounted');

        const { sites, actions } = window.redirectMate;

        if (sites && sites.length) {
            this.sites = sites;
        }

        this.actions = actions;

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
                        <option value="all">{{ Craft.t('redirectmate', 'All sites') }}</option>
                        <option v-for="item in sites" :value="item.id">{{ item.name }}</option>
                    </select>
                </div>

                <div class="select">
                    <select v-model="serverParams.sortBy" @change="updateTable">
                        <option value="newest">{{ Craft.t('redirectmate', 'Newest') }}</option>
                        <option value="hits">{{ Craft.t('redirectmate', 'By hits') }}</option>
                        <option value="lasthit">{{ Craft.t('redirectmate', 'Last hit') }}</option>
                    </select>
                </div>

                <!-- Actions menu -->
                <div :class="{ hidden: selectedItems.length === 0 }">
                    <div data-icon="settings" class="btn menubtn"></div>
                    <div data-align="left" class="menu">
                        <ul role="listbox">
                            <li class="cursor-pointer" @click="batchDeleteItems"><a>{{ Craft.t('redirectmate', 'Delete') }}</a></li>
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
                <button @click="addRedirect" class="btn submit add icon">{{ Craft.t('redirectmate', 'New redirect') }}</button>
            </div>
        </div>

        <div class="flex" v-if="items == null">
            <span class="spinner mx-auto py-70"></span>
        </div>

        <div class="w-100" v-if="items && items.length == 0">
            <p class="mx-auto py-70 text-center">{{ Craft.t('redirectmate', 'No redirects.') }}</p>
        </div>

        <div class="w-100">
            <div class="tableview tablepane mt-20" v-if="items && items.length > 0">
                <table class="data w-100">
                    <thead>
                        <tr>
                            <th class="checkbox-column"><input type="checkbox" v-model="selectAll" class="relative top-2px"></th>
                            <th>{{ Craft.t('redirectmate', 'Source URL') }}</th>
                            <th>{{ Craft.t('redirectmate', 'Status') }}</th>
                            <th>{{ Craft.t('redirectmate', 'Destination URL') }}</th>
                            <th>{{ Craft.t('redirectmate', 'Site') }}</th>
                            <th>
                                <div class="text-right">{{ Craft.t('redirectmate', 'Hits') }}</div>
                            </th>
                            <th>
                                <div class="text-center">{{ Craft.t('redirectmate', 'Match by') }}</div>
                            </th>
                            <th>
                                <div class="text-center">{{ Craft.t('redirectmate', 'Regexp?') }}</div>
                            </th>
                            <th>{{ Craft.t('redirectmate', 'Last hit') }}</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="group" v-for="item in items" :key="'redirectItem' + item.id">
                            <td>
                                <input type="checkbox" v-model="selectedItems" :value="item.id" class="relative top-2px">
                            </td>
                            <td class="break-all" width="40%">
                                <span v-if="item.isRegexp">{{ item.sourceUrl }}</span>
                                <a v-else :href="getItemUrl(item, item.sourceUrl)" class="go" target="_blank">{{ item.sourceUrl }}</a>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ item.statusCode }}
                            </td>
                            <td class="break-all">
                                <span v-if="item.isRegexp">{{ item.destinationUrl }}</span>
                                <a v-else :href="getItemUrl(item, item.destinationUrl)" class="go" target="_blank" :data-icon="!item.destinationUrl || item.destinationUrl === '/' ? 'home' : false">{{ item.destinationUrl != '/' ? item.destinationUrl : '' }}</a>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ getSiteName(item.siteId) }}
                            </td>
                            <td class="text-right">
                                {{ item.hits }}
                            </td>
                            <td class="text-center whitespace-nowrap">
                                {{ item.matchBy == 'fullurl' ? Craft.t('redirectmate', 'Full URL') : Craft.t('redirectmate', 'Path only') }}
                            </td>
                            <td class="text-center whitespace-nowrap">
                                {{ item.isRegexp == true ? Craft.t('redirectmate', 'Yes') : Craft.t('redirectmate', 'No') }}
                            </td>
                            <td :title="`${Craft.t('redirectmate', 'Created at: {date}', {
                                date: item.dateCreated
                            })}`">
                                {{ formatDateTime(item.lastHit) }}
                            </td>
                            <td>
                                <div class="text-right">
                                    <button @click="editRedirect(item.id)" class="btn small">{{ Craft.t('redirectmate', 'Edit') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <IndexViewFooter
                :current-page="serverParams.page"
                :per-page="serverParams.perPage"
                :total-count="totalCount"
                :export-action="actions.exportRedirects"
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
