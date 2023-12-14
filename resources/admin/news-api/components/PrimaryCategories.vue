<template>
	<div class="p-4">
		<div class="mb-4 flex justify-end">
			<ShaplaButton @click="openNewCategory" theme="primary" outline size="small">Add New Category</ShaplaButton>
		</div>
		<div v-for="(cat,index) in state.categories"
			 class="shadow bg-white p-2 mb-2 relative rounded flex justify-between items-center"
			 :class="{'border border-solid border-green-600':cat.value === defaultCategory}">
			<div class="flex space-x-2 justify-center">
				<div class="font-bold">{{ cat.label }}</div>
				<div class="text-xs italic">
					<template v-if="cat.value.length">
						({{ cat.value }})
					</template>
					<template v-else>
						It will generate automatically when you save the settings.
					</template>
				</div>
			</div>
			<div v-if="cat.value !== defaultCategory">
				<ShaplaCross @click="deleteCategory(cat,index)"/>
			</div>
		</div>
	</div>
	<ShaplaModal :active="state.showAddNewModal" @close="state.showAddNewModal = false" title="Add New Category"
				 content-size="small">
		<ShaplaInput
			label="Label"
			v-model="state.category.label"
		/>
		<template v-slot:foot>
			<ShaplaButton theme="primary" @click="addNewCategory">Add to List</ShaplaButton>
		</template>
	</ShaplaModal>
</template>

<script lang="ts" setup>
import {onMounted, PropType, reactive, watch} from "vue";
import {Dialog, Notify, ShaplaButton, ShaplaCross, ShaplaInput, ShaplaModal} from "@shapla/vue-components";

const props = defineProps({
	categories: {type: Array as PropType<{ label: string; value: string }[]>, default: () => []},
	defaultCategory: {type: String, default: ''}
});
const state = reactive<{
	categories: { label: string; value: string }[],
	showAddNewModal: boolean;
	category: { label: string; value: string },
}>({
	categories: [],
	showAddNewModal: false,
	category: {
		label: '',
		value: '',
	}
})
const emit = defineEmits(['change:categories']);

const openNewCategory = () => {
	state.showAddNewModal = true;
}

const addNewCategory = () => {
	state.categories.push(state.category);
	state.category = {label: '', value: ''}
	state.showAddNewModal = false;
}

const deleteCategory = (cat, index) => {
	if (cat.value === props.defaultCategory) {
		return Notify.error('You cannot delete default category.', 'Error!');
	}
	Dialog.confirm(`Are you sure to delete the category "${cat.label}"?`).then(confirmed => {
		if (confirmed) {
			state.categories.splice(index, 1);
		}
	});
}

watch(
	() => props.categories,
	newValue => {
		state.categories = newValue;
	},
	{deep: true}
)

watch(
	() => state.categories,
	newValue => {
		emit('change:categories', newValue)
	},
	{deep: true}
)

onMounted(() => {
	state.categories = props.categories;
})
</script>
