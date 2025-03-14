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
            hasOpenSelectModal: false,
            isAtEnd: false,
            currentEditId: null,

            errorMessage: null,
            errors: null,

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
    computed: {
        hasErrors() {
            return this.errors || this.errorMessage;
        },
        validationErrors() {
            // Return a flattened list of validation errors, without the keys
            return Object.values(this.errors || {}).reduce((carry, errors) => carry.concat(errors), []);
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
                    this.focusToFirstEmptyInput();
                });
            }
        },
        errors(newValue, oldValue) {
            if (!newValue) {
                return;
            }
            this.$nextTick(this.focusToFirstErrorInput);
        }
    },
    methods: {
        openElementSelect() {
            this.hasOpenSelectModal = true;
            
            const modal = window.Craft.createElementSelectorModal('craft\\elements\\Entry', {
                defaultSiteId: this.currentData.site != 'all' ? this.currentData.site : null,
                onSelect: e => {
                    const url = e[0].url;
                    const selectedElementSiteId = e[0].siteId;
                    const site = this.sites.find( ({ id }) => id === parseInt(selectedElementSiteId, 10));
                    this.currentData.destinationUrl = '/' + url.replace(site.baseUrl, '');
                },
                onHide: e => {
                    setTimeout(() => {
                        this.hasOpenSelectModal = false;
                    }, 150);
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
            this.errorMessage = null;
            this.errors = null;
        },
        getItemWithId(id) {
            return this.logItems.find(item => item.id === id);
        },
        getNextItem() {
            return this.logItems.find(item => !this.processedIds.includes(item.id) && item.handled === false);
        },
        focusToFirstEmptyInput() {
            if (this.currentData.sourceUrl === '') {
                this.$refs.sourceUrlInput.focus();
            } else {
                this.$refs.destionationUrlInput.focus();
            }
        },
        focusToFirstErrorInput() {
            const errorInput = this.$el.querySelector('.errors input');
            if (errorInput) {
                errorInput.focus();
            }
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
            this.isLoading = true;

            const data = this.currentData;

            if (this.mode === 'edit' && this.currentEditId !== null) {
                data.id = this.currentEditId;
            }

            this.$axios.post(window.redirectMate.actions.addRedirect, { redirectData: this.currentData })
                .then(({ data }) => {
                    this.errorMessage = null;
                    this.errors = null;

                    if (saveMode === 'save') {
                        this.closeCallback();
                    } else if (saveMode === 'saveAndAdd') {
                        this.currentEditId = null;
                        this.updateCurrent();
                        this.$nextTick(this.focusToFirstEmptyInput);
                    } else if (saveMode === 'saveAndContinue') {
                        this.processCurrent();
                        this.nextItem();
                    } else {
                        console.error(`Unknown save mode "${saveMode}"`);
                        throw new Error();
                    }

                    this.isLoading = false;

                    window.Craft.cp.displaySuccess(
                        Craft.t('redirectmate', 'Redirect saved.'),
                        {
                            details: `<a href="${data.sourceUrl}" class="go break-all" target="_blank" rel="noopener noreferrer">${data.sourceUrl}</a>`
                        }
                    );

                })
                .catch(error => {
                    console.error(error);
                    this.isLoading = false;
                    const { errors, message: errorMessage } = error.response.data || {};
                    if (errors) {
                        this.errors = errors;
                        this.errorMessage = null;
                    } else {
                        this.errors = null;
                        this.errorMessage = errorMessage || error.message || Craft.t('redirectmate', 'An error occurred.');
                    }
                    console.log('error!', this.errors, this.errorMessage);
                });
        }
    },

    mounted() {
        document.addEventListener('keydown', e => {
            if (e.keyCode === 27) {
                if (this.isVisible && !this.hasOpenSelectModal) {
                    this.cancel();
                }
            }
        });
    }
}
</script>

<template>
    <div @mousedown="closeCallback()" class="create-redirect-modal fixed full justify-center modal-shade z-99 flex visible" v-if="isVisible">
        <form @mousedown.stop="" class="relative flex flex-col bg-white justify-between w-[calc(100%-16px)] max-w-[550px] rounded-5px overflow-hidden modal-box-shadow">
            <div class="w-100 p-30">
                <h1>{{ mode == 'edit' ? Craft.t('redirectmate', 'Edit redirect') : Craft.t('redirectmate', 'Create redirect') }}</h1>
                <div>
                    <div class="field" v-if="sites && sites.length > 1">
                        <div class="heading">
                            <label>{{ Craft.t('redirectmate', 'Site') }}</label>
                        </div>
                        <div class="select">
                            <select v-model="currentData.site" class="min-w-[200px]">
                                <option value="all">{{ Craft.t('redirectmate', 'All sites') }}</option>
                                <option v-for="item in sites" :value="item.id">{{ item.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="field flex w-100 flex-nowrap">
                        <div class="field w-100">
                            <div class="heading">
                                <label>{{ Craft.t('redirectmate', 'Source URL') }}</label>
                            </div>
                            <div class="input" :class="{ errors: errors && errors.sourceUrl }">
                                <input ref="sourceUrlInput" class="nicetext text fullwidth" type="text" v-model="currentData.sourceUrl">
                            </div>
                        </div>
                    </div>

                    <div class="field flex w-100 flex-nowrap -mt-15">
                        <div class="field flex">
                            <div class="heading pt-6 mr-5">
                                <label>{{ Craft.t('redirectmate', 'Match as') }}</label>
                            </div>
                            <div class="select">
                                <select v-model="currentData.matchAs">
                                    <option value="exact">{{ Craft.t('redirectmate', 'Exact Match') }}</option>
                                    <option value="regexp">{{ Craft.t('redirectmate', 'Regexp') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="field flex ml-10" v-if="currentData.matchAs === 'regexp'">
                            <div class="heading pt-6 mr-5">
                                <label>{{ Craft.t('redirectmate', 'Match by') }}</label>
                            </div>
                            <div class="select">
                                <select v-model="currentData.matchBy">
                                    <option value="pathonly">{{ Craft.t('redirectmate', 'Path only') }}</option>
                                    <option value="fullurl">{{ Craft.t('redirectmate', 'Full URL') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="field flex w-100 flex-nowrap">
                        <div class="field w-100">
                            <div class="heading">
                                <label>{{ Craft.t('redirectmate', 'Destination URL') }}</label>
                            </div>

                            <div class="input relative" :class="{ errors: errors && errors.destinationUrl }">
                                <input ref="destionationUrlInput" class="nicetext text fullwidth" type="text" v-model="currentData.destinationUrl">
                                <button type="button" @click.prevent="openElementSelect" class="icon search absolute right-0 top-0 w-40px h-34px bg-black bg-opacity-5 hover:bg-opacity-7 transition-colors duration-200 border-1 border-black border-opacity-5" aria-label="Search for element"></button>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <div class="heading">
                            <label>{{ Craft.t('redirectmate', 'Redirect Type') }}</label>
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

              <div class="w-full pt-20 errors" v-if="hasErrors">
                <p class="text-red-500" v-if="errorMessage">{{ this.errorMessage }}</p>
                <ul class="errors" v-if="validationErrors">
                  <li class="text-red-500" v-for="error in validationErrors">{{ error }}</li>
                </ul>
              </div>

            </div>

            <div class="modal-footer w-100 flex-grow-0 flex justify-between">

                <div class="flex justify-center items-center">
                    <div ref="spinner" class="spinner block w-24px h-24px" :style="{ opacity: isLoading ? 1 : 0 }"></div>
                </div>

                <div class="flex justify-end">
                    <button type="button" @click.prevent="cancel" class="btn">{{ Craft.t('redirectmate', 'Cancel') }}</button>

                    <button type="submit" v-if="this.mode != 'batch'" @click.prevent="save" class="btn submit">{{ Craft.t('redirectmate', 'Save') }}</button>
                    <button type="submit" v-if="this.mode != 'batch'" @click.prevent="saveAndAdd" class="btn submit">{{ Craft.t('redirectmate', 'Save and add another') }}</button>

                    <button type="button" v-if="this.mode === 'batch'" @click.prevent="skip" class="btn">{{ Craft.t('redirectmate', 'Skip') }}</button>
                    <button type="submit" v-if="this.mode === 'batch'" @click.prevent="saveAndContinue" class="btn submit">{{ Craft.t('redirectmate', 'Create and continue') }}</button>
                </div>
            </div>

            <div class="absolute full bg-white bg-opacity-95 z-2 p-40 flex justify-center" v-if="isAtEnd">
                <div class="text-center w-100">
                    <p>{{ Craft.t('redirectmate', 'No more unhandled errors!') }}</p>
                    <button @click.prevent="cancel" class="btn submit">{{ Craft.t('redirectmate', 'Close') }}</button>
                </div>
            </div>
        </form>
    </div>
</template>
