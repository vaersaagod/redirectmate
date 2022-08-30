<script>
export default {
    inject: ['Craft'],
    props: {
        currentPage: Number, // The current view's current page number
        perPage: Number, // The number of items to display per page in the current view
        totalCount: Number, // The total number of items in the current view
        exportAction: String // An action that will export the items in the current view
    },
    methods: {
        updateCurrentPage(value) {
            this.$emit('currentPageChange', value);
        },
        updatePerPage(value) {
            this.$emit('perPageChange', value);
            this.updateCurrentPage(1); // Jump back to page 1 when the per page number changes
        }
    },
    computed: {
        totalPages() {
            return Math.ceil(this.totalCount / this.perPage);
        },
        isFirstPage() {
            return !this.totalCount || this.currentPage <= 1;
        },
        isLastPage() {
            return !this.totalCount || this.currentPage >= this.totalPages;
        }
    },
    emits: [
      'perPageChange',
      'currentPageChange'
    ]
};
</script>

<template>
    <div class="flex justify-between sticky bottom-0 mt-12 py-15 bg-white border-t-1 border-gray-100 z-2 -m-24 px-24">
        <div class="flex pagination">
            <nav class="flex" aria-label="entry pagination">
                <button role="button" class="page-link prev-page" :class="{ disabled: isFirstPage }" :disabled="isFirstPage" :title="Craft.t('redirectmate', 'Previous page')" @click="updateCurrentPage(currentPage - 1)"></button>
                <button role="button" class="page-link next-page" :class="{ disabled: isLastPage }" :disabled="isLastPage" :title="Craft.t('redirectmate', 'Next page')" @click="updateCurrentPage(currentPage + 1)"></button>
            </nav>
            <div class="page-info text-gray-500" v-if="totalCount">{{
                Craft.t('redirectmate', '{from}-{to} of {total}', {
                    from: ((currentPage - 1) * perPage) + 1,
                    to: Math.min(currentPage * perPage, totalCount),
                    total: totalCount
                })
            }}</div>
        </div>
        <div class="flex">
            <div class="flex">
                <span class="text-gray-500">{{ Craft.t('redirectmate', 'Display') }}:</span>
                <div class="select">
                    <select name="limit" :value="perPage" @change="updatePerPage($event.target.value)">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <a v-if="exportAction" :href="totalCount ? exportAction : false" class="btn"
               :class="{ disabled: !totalCount }"
            >{{ Craft.t('redirectmate', 'Export') }}</a>
        </div>
    </div>
</template>
