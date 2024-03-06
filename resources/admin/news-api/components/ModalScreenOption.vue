<script setup lang="ts">
import {ShaplaButton, ShaplaModal} from "@shapla/vue-components";
import {onMounted, PropType, reactive, watch} from "vue";

interface ColumnInterface {
  key: string;
  label: string;
}

const emit = defineEmits<{
  close: [],
  'change:excludedColumns': [value: string[]];
  'update': [value: { excluded_columns: string[] }];
}>()

const props = defineProps({
  active: {type: Boolean, default: false},
  columns: {type: Array as PropType<ColumnInterface[]>, default: () => []},
  excludedColumns: {type: Array as PropType<string[]>, default: () => []},
  perPage: {type: Number, default: 20},
})

const state = reactive<{
  columns: ColumnInterface[];
  excludedColumns: string[];
  userColumns: string[];
}>({
  columns: [],
  excludedColumns: [],
  userColumns: [],
})

const emitClose = () => emit('close');
const onSubmit = () => emit('update', {excluded_columns: state.excludedColumns});

const getUserColumns = (excludedColumns: string[]) => {
  return state.columns
      .filter(column => !excludedColumns.includes(column.key))
      .map(column => column.key);
}

watch(() => props.columns, newValue => state.columns = newValue);
watch(() => props.excludedColumns, newValue => state.userColumns = getUserColumns(newValue));
watch(() => state.userColumns, newValue => {
  const excludedColumns = getUserColumns(newValue)
  state.excludedColumns = excludedColumns;
  emit("change:excludedColumns", excludedColumns);
});

onMounted(() => {
  state.columns = props.columns;
  state.userColumns = getUserColumns(props.excludedColumns);
})
</script>

<template>
  <ShaplaModal :active="active" title="Screen Option" @close="emitClose">
    <fieldset class="mb-2">
      <legend class="font-bold pb-2 text-xl">Columns</legend>
      <div class="flex flex-col">
        <label v-for="column in state.columns" :key="column.key" class="inline-flex items-center mb-1">
          <input type="checkbox" :value="column.key" v-model="state.userColumns">
          <span>{{ column.label }}</span>
        </label>
      </div>
    </fieldset>
    <template v-slot:foot>
      <ShaplaButton theme="primary" @click="onSubmit">Update</ShaplaButton>
    </template>
  </ShaplaModal>
</template>
