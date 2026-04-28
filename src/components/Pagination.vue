<template>
  <div class="pagination-container flex items-center justify-between mt-4 no-print-detail" v-if="totalItems > 0">
    <div class="text-sm text-gray-600">
      Showing {{ startIndex + 1 }} to {{ Math.min(endIndex, totalItems) }} of {{ totalItems }} entries
    </div>
    <div class="flex items-center gap-2">
      <button 
        class="btn btn-outline px-3 py-1 text-sm min-h-[32px]" 
        :disabled="currentPage === 1"
        @click="$emit('update:currentPage', currentPage - 1)"
      >
        Previous
      </button>
      
      <span class="text-sm font-medium mx-2">
        Page {{ currentPage }} of {{ totalPages }}
      </span>
      
      <button 
        class="btn btn-outline px-3 py-1 text-sm min-h-[32px]" 
        :disabled="currentPage === totalPages || totalPages === 0"
        @click="$emit('update:currentPage', currentPage + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalItems: { type: Number, required: true },
  itemsPerPage: { type: Number, default: 10 }
})

defineEmits(['update:currentPage'])

const totalPages = computed(() => Math.ceil(props.totalItems / props.itemsPerPage) || 1)
const startIndex = computed(() => (props.currentPage - 1) * props.itemsPerPage)
const endIndex = computed(() => startIndex.value + props.itemsPerPage)
</script>
