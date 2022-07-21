<script>
export default {
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
                perPage: 50,
                handled: 'all',
                site: 'all',
                sortBy: 'newest'
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
            this.$axios.post(window.redirectMate.actions.getRedirects, this.serverParams)
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
            
            this.$axios.post(window.redirectMate.actions.deleteRedirects, { ids: this.selectedItems })
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
                return site ? site.name : 'Unknown';
            } else {
                return 'All sites';
            }
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
                    <select v-model="serverParams.sortBy" @change="updateTable">
                        <option value="newest">Newest first</option>
                        <option value="hits">By hits</option>
                        <option value="lasthit">Last hit</option>
                    </select>
                </div>

                <div data-icon="settings" class="btn menubtn" :class="{ hidden: selectedItems.length === 0 }"></div>

                <div data-align="left" class="menu">
                    <ul role="listbox">
                        <li @click="batchDeleteItems"><a>Delete</a></li>
                    </ul>
                </div>
            </div>

            <div class="flex">
                <button class="btn disabled" disabled>Export</button>
                <button @click="addRedirect" class="btn submit add icon">New redirect</button>
            </div>
        </div>

        <div class="flex" v-if="items == null">
            <span class="spinner mx-auto py-70"></span>
        </div>

        <div class="w-100" v-if="items && items.length == 0">
            <p class="mx-auto py-70 text-center">No redirects.</p>
        </div>

        <div class="w-100" v-if="items && items.length > 0">
            <table class="data w-100 mt-20">
                <thead>
                <tr>
                    <th class="checkbox-column"><input type="checkbox" v-model="selectAll" class="relative top-2px"></th>
                    <th>Source URL</th>
                    <th>Status</th>
                    <th>Destination URL</th>
                    <th>Site</th>
                    <th>Match by</th>
                    <th>Regexp?</th>
                    <th>
                        <div class="text-right">Hits</div>
                    </th>
                    <th>Last Hit</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>

                <tbody>
                <tr class="group" v-for="item in items" :key="'redirectItem' + item.id">
                    <td>
                        <input type="checkbox" v-model="selectedItems" :value="item.id" class="relative top-2px">
                    </td>
                    <td>
                        <a @click="editRedirect(item.id)" target="_blank">{{ item.sourceUrl }}</a>
                    </td>
                    <td>
                        {{ item.statusCode }}
                    </td>
                    <td>
                        <a href="" target="_blank">{{ item.destinationUrl }}</a>
                    </td>
                    <td>
                        {{ getSiteName(item.siteId) }}
                    </td>
                    <td>
                        {{ item.matchBy }}
                    </td>
                    <td>
                        {{ item.isRegexp == true ? 'Yes' : 'No' }}
                    </td>
                    <td>
                        <div class="text-right">
                            {{ item.hits }}
                        </div>
                    </td>
                    <td :title="'Created at: '+item.dateCreated">
                        {{ item.lastHit }}
                    </td>
                    <td>
                        <div class="text-right">
                            <button @click="editRedirect(item.id)" class="add icon rounded-100 w-24px h-24px text-link opacity-60 group-hover:opacity-100 bg-gray-200" title="Edit"></button>
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
