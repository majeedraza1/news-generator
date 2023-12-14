<script setup lang="ts">
import {Dialog, ShaplaButton, ShaplaInput, ShaplaModal, ShaplaTable} from "@shapla/vue-components";
import {onMounted, PropType, reactive} from "vue";
import http from "../../../utils/axios";
import CrudOperation from "../../../utils/CrudOperation";

interface BlacklistWordInterface {
	id: number;
	phrase: string;
}

const props = defineProps({
	words: {type: Array as PropType<BlacklistWordInterface[]>, default: () => []}
});

const state = reactive<{
	items: BlacklistWordInterface[]
	openAddNewModal: boolean;
	openEditModal: boolean;
	word: string;
	activeItem: BlacklistWordInterface;
}>({
	items: [],
	openAddNewModal: false,
	openEditModal: false,
	word: '',
	activeItem: null,
})

const crud = new CrudOperation('openai/blacklist-words', http);

const closeAddNewModal = () => {
	state.openAddNewModal = false;
	state.word = '';
}
const closeEditModal = () => {
	state.openEditModal = false;
	state.activeItem = null;
}
const getItems = () => {
	crud.getItems().then((data) => {
		state.items = data.items as BlacklistWordInterface[];
	})
}
const addNewPhrase = () => {
	crud.createItem({phrase: state.word}).then(() => {
		closeAddNewModal();
		getItems();
	})
}
const updatePhrase = () => {
	crud.updateItem(state.activeItem.id, {phrase: state.activeItem.phrase}).then(() => {
		closeEditModal();
		getItems();
	})
}
const deleteItem = (id: number) => {
	Dialog.confirm('Are you sure to delete?').then(confirmed => {
		if (confirmed) {
			crud.deleteItem(id).then(() => {
				getItems();
			});
		}
	})
}
const onActionClick = (action: string, item: BlacklistWordInterface) => {
	if ('edit' === action) {
		state.openEditModal = true;
		state.activeItem = item;
	}
	if ('delete' === action) {
		deleteItem(item.id);
	}
}

onMounted(() => {
	getItems();
})
</script>

<template>
	<div class="flex justify-end">
		<ShaplaButton theme="secondary" size="small" @click="state.openAddNewModal = true">Add New</ShaplaButton>
	</div>
	<div class="my-2">
		<ShaplaTable
			:columns="[{key:'phrase',label:'Phrase'}]"
			:actions="[{key:'edit',label:'Edit'},{key:'delete',label:'Delete'}]"
			:items="state.items"
			@click:action="onActionClick"
		/>
	</div>
	<ShaplaModal title="Add New Phrase" :active="state.openAddNewModal" @close="closeAddNewModal">
		<ShaplaInput type="textarea" label="Words/Phrase" v-model="state.word"/>
		<template v-slot:foot>
			<ShaplaButton theme="primary" @click="addNewPhrase">Create</ShaplaButton>
		</template>
	</ShaplaModal>
	<ShaplaModal v-if="state.activeItem" title="Edit Phrase" :active="state.openEditModal" @close="closeEditModal">
		<ShaplaInput type="textarea" label="Words/Phrase" v-model="state.activeItem.phrase"/>
		<template v-slot:foot>
			<ShaplaButton theme="primary" @click="updatePhrase">Update</ShaplaButton>
		</template>
	</ShaplaModal>
</template>
