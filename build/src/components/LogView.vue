<script>
import isbot from 'isbot';
import UAParser from 'ua-parser-js';

export default {
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

            serverParams: {
                page: 1,
                perPage: 50,
                handled: 'all',
                site: 'all',
                sortBy: 'hits'
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
        }
    },

    watch: {},

    methods: {
        loadItems() {
            this.$axios.post(window.redirectMate.actions.getLogs, this.serverParams)
                .then(({ data }) => {
                    console.log(data);
                    this.totalCount = data.count;
                    this.items = data.data;
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
            if (window.confirm('Are you sure you want to clear the log?')) {
                this.$axios.post(window.redirectMate.actions.deleteAllLogItems, {})
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
            
            this.$axios.post(window.redirectMate.actions.checkLogItem, { id: nextId })
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

            this.$axios.post(window.redirectMate.actions.deleteLogItems, { ids: this.selectedItems })
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
            const site = this.sites.find( ({ id }) => id === item.siteId );
            return this.Craft.getUrl(item.sourceUrl, null, site.baseUrl);
        }
    },

    mounted() {
        console.log('mounted');

        if (window.redirectMate.sites && window.redirectMate.sites.length > 0) {
            this.sites = window.redirectMate.sites;
        }

        this.loadItems();
        Craft.initUiElements();
    }
}
</script>

<template>
    <div class="flex flex-col">
        <div class="flex w-100 justify-between">
            <div class="flex">

                <div class="select">
                    <select v-model="serverParams.site" @change="updateTable">
                        <option value="all">All sites</option>
                        <option v-for="item in sites" :value="item.id">{{ item.name }}</option>
                    </select>
                </div>

                <div class="select">
                    <select v-model="serverParams.handled" @change="updateTable">
                        <option value="all">All errors</option>
                        <option value="handled">Handled</option>
                        <option value="nothandled">Not Handled</option>
                    </select>
                </div>
                
                <div class="select">
                    <select v-model="serverParams.sortBy" @change="updateTable">
                        <option value="hits">By hits</option>
                        <option value="lasthit">Last hit</option>
                    </select>
                </div>

                <div data-icon="settings" class="btn menubtn" :class="{ hidden: selectedItems.length === 0 }"></div>

                <div data-align="left" class="menu">
                    <ul role="listbox">
                        <li @click="batchDeleteItems"><a>Delete</a></li>
                        <li><a @click="batchCheckItems">Check</a></li>
                    </ul>
                </div>
            </div>

            <div class="flex">
                <button class="btn disabled" disabled>Export</button>
                <button @click="clearLog" class="btn delete icon">Clear log</button>
                <button @click="resolve" class="btn submit add icon">Resolve</button>
            </div>
        </div>

        <div class="flex" v-if="items == null">
            <span class="spinner mx-auto py-70"></span>
        </div>

        <div class="w-100" v-if="items && items.length == 0">
            <p class="mx-auto py-70 text-center">No errors have been logged.</p>
        </div>

        <div class="w-100" v-if="items && items.length > 0">
            <table class="data w-100 mt-20">
                <thead>
                <tr>
                    <th class="checkbox-column"><input type="checkbox" v-model="selectAll" class="relative top-2px"></th>
                    <th>Source URL</th>
                    <th>Remote IP</th>
                    <th>User Agent</th>
                    <th>Referrer</th>
                    <th>
                        <div class="text-center">Hits</div>
                    </th>
                    <!--<th>Last Hit</th>-->
                    <th>&nbsp;</th>
                </tr>
                </thead>

                <tbody>
                <tr class="group" v-for="logItem in items" :key="'logItem' + logItem.id">
                    <td>
                        <input type="checkbox" v-model="selectedItems" :value="logItem.id" class="relative top-2px">
                    </td>
                    <td>
                        <span class="status-dot inline-block w-10px h-10px rounded-100 mr-10" :class="{ 'bg-green-600': logItem.handled || newlyHandledItems.includes(logItem.id), 'bg-red-600': !logItem.handled && !newlyHandledItems.includes(logItem.id), 'is-checking': checkingItems.includes(logItem.id) }" :title="logItem.handled ? 'Handled' : 'Not handled'"></span>
                        <a :href="getItemSourceUrl(logItem)" target="_blank">{{ logItem.sourceUrl }}</a>
                    </td>
                    <td>
                        <span v-if="logItem.remoteIp === '127.0.0.1'" class="inline-block">{{ logItem.remoteIp }}</span>
                        <a v-if="logItem.remoteIp !== '127.0.0.1'" class="inline-block" :href="'https://whatismyipaddress.com/ip/' + logItem.remoteIp" target="_blank">{{ logItem.remoteIp }}</a>
                    </td>
                    <td :title="logItem.userAgent">
                        <span class="capitalize" v-if="isUaBot(logItem.userAgent)">{{ getBot(logItem.userAgent) }}</span>
                        <span v-else>{{ getBrowser(logItem.userAgent) }}</span>

                    </td>
                    <td>
                        {{ logItem.referrer }}
                    </td>
                    <td :title="'Last hit: ' + logItem.lastHit + '.\nCreated: ' + logItem.dateCreated">
                        <div class="text-center">
                            {{ logItem.hits }}
                        </div>
                    </td>
                    <!--
                    <td :title="'Created at: ' + logItem.dateCreated">
                        {{ logItem.lastHit }}
                    </td>
                    -->
                    <td>
                        <div class="text-right">
                            <button v-if="!logItem.handled" @click="addRedirectForId(logItem.id)" class="add icon rounded-100 w-24px h-24px text-link opacity-60 group-hover:opacity-100 bg-gray-200" title="Add"></button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>

            <div class="mt-40 flex justify-between">
                <p class="text-gray-500">Displaying {{ ((serverParams.page - 1) * serverParams.perPage) + 1 }} to {{ Math.min(serverParams.page * serverParams.perPage, totalCount) }} of {{ totalCount }} items</p>
                <div class="flex">
                    <span class="text-gray-500">Display:</span>
                    <div class="select">
                        <select name="limit" v-model="serverParams.perPage" @change="updateTable">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
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
