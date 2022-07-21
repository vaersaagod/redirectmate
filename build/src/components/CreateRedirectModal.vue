<script>
export default {
    inject: ['$axios', 'Craft'],
    props: {
        isVisible: Boolean,
        mode: String,
        editId: Number,
        logItems: Array,
        sites: Array,
        parentSelectedSite: [Number, String],
        closeCallback: Function,
    },
    data() {
        return {
            isLoading: false,
            isAtEnd: false,
            currentEditId: null,
            message: {
                isError: false,
                text: '',
            },

            currentData: {
                logId: '',
                site: 'all',
                sourceUrl: '',
                matchBy: 'pathonly',
                destinationUrl: '',
                matchAs: 'exact',
                statusCode: '301',
            },

            dataDefaults: {
                logId: '',
                site: 'all',
                sourceUrl: '',
                matchBy: 'pathonly',
                destinationUrl: '',
                matchAs: 'exact',
                statusCode: '301',
            },

            processedIds: []
        }
    },
    watch: {
        isVisible(newStatus, oldStatus) {
            if (newStatus === true) {
                this.isAtEnd = false;
                this.processedIds = [];

                this.currentEditId = this.editId;

                this.$nextTick(() => {
                    this.updateCurrent();

                    if (this.currentData.sourceUrl === '') {
                        this.$refs.sourceUrlInput.focus();
                    } else {
                        this.$refs.destionationUrlInput.focus();
                    }
                });
            }
        }
    },
    methods: {
        openElementSelect() {
            const modal = window.Craft.createElementSelectorModal('craft\\elements\\Entry', {
                onSelect: e => {
                    const url = new URL(e[0].url);
                    this.currentData.destinationUrl = url.pathname;
                }
            });
        },
        resetCurrentData() {
            this.currentData.id = null;
            this.currentData.logId = this.dataDefaults.logId;
            this.currentData.site = this.dataDefaults.site;
            this.currentData.sourceUrl = this.dataDefaults.sourceUrl;
            this.currentData.matchBy = this.dataDefaults.matchBy;
            this.currentData.destinationUrl = this.dataDefaults.destinationUrl;
            this.currentData.matchAs = this.dataDefaults.matchAs;
            this.currentData.statusCode = this.dataDefaults.statusCode;
        },
        getItemWithId(id) {
            return this.logItems.find(item => item.id === id);
        },
        getNextItem() {
            return this.logItems.find(item => !this.processedIds.includes(item.id) && item.handled === 0);
        },
        updateCurrent() {
            this.resetCurrentData();

            this.currentData.site = this.parentSelectedSite;

            const currentItem = this.currentEditId !== null ? this.getItemWithId(this.currentEditId) : this.getNextItem();

            if (currentItem === undefined) {
                return false;
            }

            this.currentData.logId = currentItem.id;
            this.currentData.sourceUrl = currentItem.sourceUrl;
            this.currentData.site = !currentItem.siteId ? 'all' : currentItem.siteId;

            if (this.mode === 'edit' && this.currentEditId !== null) {
                this.currentData.matchBy = currentItem.matchBy;
                this.currentData.destinationUrl = currentItem.destinationUrl;
                this.currentData.matchAs = currentItem.isRegexp ? 'regexp' : 'exact';
                this.currentData.statusCode = currentItem.statusCode;
            }

            return true;
        },
        processCurrent() {
            if (this.currentData.logId !== '') {
                this.processedIds.push(this.currentData.logId);
            }
        },
        nextItem() {
            let hasMore = this.updateCurrent();

            if (hasMore) {
                this.$refs.destionationUrlInput.focus();
            } else {
                this.isAtEnd = true;
            }
        },
        cancel() {
            this.closeCallback();
        },
        skip() {
            this.processCurrent();
            this.nextItem();
        },
        save() {
            this.doSave('save')
        },
        saveAndAdd() {
            this.doSave('saveAndAdd')
        },
        saveAndContinue() {
            this.doSave('saveAndContinue')
        },

        doSave(saveMode) {
            console.log(window.redirectMate.actions.addRedirect);
            this.message.isError = false;
            this.message.text = '';
            this.isLoading = true;

            const data = this.currentData;

            if (this.mode === 'edit' && this.currentEditId !== null) {
                data.id = this.currentEditId;
            }

            this.$axios.post(window.redirectMate.actions.addRedirect, { redirectData: this.currentData })
                .then(({ data }) => {
                    console.log(data);

                    if (saveMode === 'save') {
                        this.closeCallback();
                    } else if (saveMode === 'saveAndAdd') {
                        this.currentEditId = null;
                        this.updateCurrent();
                    } else {
                        this.processCurrent();
                        this.nextItem();
                    }

                    this.isLoading = false;
                })
                .catch(error => {
                    console.error(error);
                    this.isLoading = false;
                    this.message.isError = true;
                    this.message.text = error.response.data.message || error.message;
                });
        }
    },

    mounted() {
        document.addEventListener('keyup', e => {
            if (e.keyCode === 27) {
                if (this.isVisible) {
                    this.cancel();
                }
            }
        });
    }
}
</script>

<template>
    <div @click="closeCallback()" class="fixed full flex justify-center modal-shade z-99 flex" v-if="isVisible">
        <form @click.stop="" class="relative flex flex-col bg-white justify-between w-[calc(100%-16px)] max-w-[750px] rounded-5px overflow-hidden modal-box-shadow">
            <div class="w-100 p-40">
                <h1>Create redirect</h1>
                <div>
                    <div class="field">
                        <div class="heading">
                            <label>Site</label>
                        </div>
                        <div class="select">
                            <select v-model="currentData.site" class="min-w-[200px]">
                                <option value="all">All sites</option>
                                <option v-for="item in sites" :value="item.id">{{ item.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="field flex w-100 flex-nowrap">
                        <div class="field w-100">
                            <div class="heading">
                                <label>Source URL</label>
                            </div>
                            <div class="input">
                                <input ref="sourceUrlInput" class="nicetext text fullwidth" type="text" v-model="currentData.sourceUrl">
                            </div>
                        </div>
                        <div class="field">
                            <div class="heading">
                                <label>Match by</label>
                            </div>
                            <div class="select">
                                <select v-model="currentData.matchBy" class="w-[150px]">
                                    <option value="pathonly">Path only</option>
                                    <option value="fullurl">Full URL</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field flex w-100 flex-nowrap">
                        <div class="field w-100">
                            <div class="heading">
                                <label>Destination URL</label>
                            </div>

                            <div class="input relative">
                                <input ref="destionationUrlInput" class="nicetext text fullwidth" type="text" v-model="currentData.destinationUrl">
                                <button type="button" @click.prevent="openElementSelect" class="icon search absolute right-0 top-0 w-40px h-34px bg-black bg-opacity-5 hover:bg-opacity-7 transition-colors duration-200 border-1 border-black border-opacity-5" aria-label="Search for element"></button>
                            </div>
                        </div>

                        <div class="field">
                            <div class="heading">
                                <label>Match as</label>
                            </div>
                            <div class="select">
                                <select v-model="currentData.matchAs" class="w-[150px]">
                                    <option value="exact">Exact Match</option>
                                    <option value="regexp">Regexp</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <div class="heading">
                            <label>Redirect Type</label>
                        </div>
                        <div class="select">
                            <select v-model="currentData.statusCode" class="min-w-[200px]">
                                <option value="301">301 - Moved Permanently</option>
                                <option value="302">302 - Found</option>
                                <option value="307">307 - Temporary Redirect</option>
                                <option value="308">308 - Permanent Redirect</option>
                                <option value="410">410 - Gone</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer w-100 flex-grow-0 flex justify-between">
                <div class="flex justify-center">
                    <div :class="{ 'text-red-500': message.isError }">{{ message.text }}</div>
                    <div ref="spinner" class="spinner block w-24px h-24px" :style="{ opacity: isLoading ? 1 : 0 }"></div>
                </div>

                <div class="flex justify-end">
                    <button type="button" @click.prevent="cancel" class="btn">Cancel</button>

                    <button type="submit" v-if="this.mode != 'batch'" @click.prevent="save" class="btn submit">Save</button>
                    <button type="submit" v-if="this.mode != 'batch'" @click.prevent="saveAndAdd" class="btn submit">Save and add another</button>

                    <button type="button" v-if="this.mode === 'batch'" @click.prevent="skip" class="btn">Skip</button>
                    <button type="submit" v-if="this.mode === 'batch'" @click.prevent="saveAndContinue" class="btn submit">Create and continue</button>
                </div>
            </div>

            <div class="absolute full bg-white bg-opacity-95 z-2 p-40 flex justify-center" v-if="isAtEnd">
                <div class="text-center w-100">
                    <p>No more unhandled errors!</p>
                    <button @click.prevent="cancel" class="btn submit">Close</button>
                </div>
            </div>
        </form>
    </div>
</template>
