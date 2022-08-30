<script>
export default {
    inject: ['Craft'],
    props: {
        currentPage: Number,
        perPage: Number,
        totalCount: Number,
        exportAction: String
    },
    emits: ['perPageChanged']
};
</script>

<template>
    <div class="mt-40 flex justify-between">
        <p class="text-gray-500 mb-0">
            {{
                Craft.t('redirectmate', '{from}-{to} of {total}', {
                    from: ((currentPage - 1) * perPage) + 1,
                    to: Math.min(currentPage * perPage, totalCount),
                    total: totalCount
                })
            }}
        </p>
        <div class="flex">
            <div class="flex">
                <span class="text-gray-500">{{ Craft.t('redirectmate', 'Display') }}:</span>
                <div class="select">
                    <select name="limit" :value="perPage" @change="$emit('perPageChanged', parseInt($event.target.value, 10))">
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
