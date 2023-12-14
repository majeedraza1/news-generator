<template>
	<h1 class="wp-heading-inline">Sites</h1>
	<hr class="wp-header-end" />

	<div>
		<div class="flex justify-end">
			<ShaplaButton @click="getItems" size="small" theme="primary"
				>Reload
			</ShaplaButton>
		</div>
		<ShaplaToggles v-if="state.settings.length">
			<ShaplaToggle
				v-for="(setting, index) in state.settings"
				:key="index"
				:name="getSiteSettingName(setting, index)"
			>
				<SiteInfo :setting="setting" />
				<div class="flex space-x-2">
					<ShaplaButton
						outline
						theme="primary"
						size="small"
						@click.prevent="editSite(setting, index)"
						>Edit
					</ShaplaButton>
					<ShaplaButton
						outline
						theme="default"
						size="small"
						@click.prevent="testSite(setting, index)"
						>Test Connection
					</ShaplaButton>
					<ShaplaButton
						outline
						theme="default"
						size="small"
						@click.prevent="syncTags(setting)"
						>Sync Tags
					</ShaplaButton>
					<ShaplaButton
						outline
						theme="default"
						size="small"
						@click.prevent="syncCategories(setting)"
						>Sync Categories
					</ShaplaButton>
					<ShaplaButton
						outline
						theme="error"
						size="small"
						@click.prevent="removeSetting(setting, index)"
						>Remove
					</ShaplaButton>
				</div>
			</ShaplaToggle>
		</ShaplaToggles>
		<div v-else>
			You did not add any site yet. Click on '+' button and add your first
			site.
		</div>
	</div>
	<div class="fixed bottom-8 right-8">
		<ShaplaButton fab theme="primary" size="large" @click="addNewSetting">
			<ShaplaIcon>
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 24 24"
					class="w-4 h-4 fill-current"
				>
					<path d="M0 0h24v24H0V0z" fill="none" />
					<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
				</svg>
			</ShaplaIcon>
		</ShaplaButton>
	</div>
	<SiteAddOrEditModal
		v-if="state.showAddNewModal"
		:active="state.showAddNewModal"
		:site="state.site"
		@close="closeSiteAddNewModal"
		@save="onSaveSite"
	/>
	<SiteAddOrEditModal
		v-if="state.showEditModal"
		:active="state.showEditModal"
		:site="state.activeSite"
		@close="closeSiteEditModal"
		@save="onSaveSite"
	/>
</template>

<script lang="ts" setup>
import { onMounted, reactive } from "vue";
import {
	Dialog,
	Notify,
	ShaplaButton,
	ShaplaIcon,
	ShaplaToggle,
	ShaplaToggles,
	Spinner,
} from "@shapla/vue-components";
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import { SiteSettingInterface } from "../../../utils/interfaces";
import SiteAddOrEditModal from "../components/SiteAddOrEditModal.vue";
import SiteInfo from "../components/SiteInfo.vue";

const crud = new CrudOperation("admin/news-sites", http);

const state = reactive<{
	settings: SiteSettingInterface[];
	site: SiteSettingInterface;
	activeSite: SiteSettingInterface;
	activeSiteIndex: number;
	showAddNewModal: boolean;
	showEditModal: boolean;
}>({
	settings: [],
	site: null,
	activeSite: null,
	activeSiteIndex: -1,
	showAddNewModal: false,
	showEditModal: false,
});

const authTypes = [
	{ label: "No Auth Required", value: "None" },
	{ label: "Basic", value: "Basic" },
	{ label: "Bearer", value: "Bearer" },
	{ label: "POST parameter", value: "Post-Params" },
	{ label: "GET argument", value: "Get-Args" },
	{ label: "Append to URL", value: "Append-to-Url" },
];

const addNewSetting = () => {
	state.showAddNewModal = true;
	state.site = {
		site_url: "",
		auth_credentials: "",
		auth_username: "",
		auth_password: "",
		auth_type: "Bearer",
		auth_get_args: "",
		auth_post_params: "",
	};
};
const closeSiteAddNewModal = () => {
	state.showAddNewModal = false;
	state.site = null;
};
const closeSiteEditModal = () => {
	state.showEditModal = false;
	state.activeSite = null;
	state.activeSiteIndex = -1;
};
const onSaveSite = (site: SiteSettingInterface) => {
	if (site.id) {
		crud.updateItem(site.id, site).then(() => {
			getItems();
			closeSiteEditModal();
		});
	} else {
		crud.createItem(site).then((data: SiteSettingInterface) => {
			state.settings.unshift(data);
			closeSiteAddNewModal();
		});
	}
};
const removeSetting = (setting: SiteSettingInterface, index: number) => {
	Dialog.confirm("Are you sure to remove the site?").then((confirmed) => {
		if (confirmed) {
			crud.deleteItem(setting.id).then(() => {
				state.settings.splice(index, 1);
			});
		}
	});
};

const editSite = (setting: SiteSettingInterface, index: number) => {
	state.activeSite = setting;
	state.activeSiteIndex = index;
	state.showEditModal = true;
};
const testSite = (setting: SiteSettingInterface, index: number) => {
	Spinner.activate();
	http.post("admin/news-sites/send-general-data-to-sites", { id: setting.id })
		.then((response) => {
			state.settings[index] = response.data.data as SiteSettingInterface;
			Notify.success("Successfully connected", "Success");
		})
		.catch((error) => {
			const responseData = error.response.data;
			if (responseData.message) {
				Notify.error(responseData.message, "Error!");
			}
		})
		.finally(() => {
			Spinner.deactivate();
		});
};
const syncTags = (setting: SiteSettingInterface) => {
	Dialog.confirm("Are you sure to sync tags meta description?").then(
		(confirmed) => {
			if (confirmed) {
				Spinner.activate();
				http.post("admin/news-sites/sync-tag", { id: setting.id })
					.then((response) => {
						const data = response.data.data;
						if (data.tags_count) {
							Notify.success(
								`A background task is running to sync ${data.tags_count} tags.`,
								"Success"
							);
						}
					})
					.catch((error) => {
						const responseData = error.response.data;
						if (responseData.message) {
							Notify.error(responseData.message, "Error!");
						}
					})
					.finally(() => {
						Spinner.deactivate();
					});
			}
		}
	);
};
const syncCategories = (setting: SiteSettingInterface) => {
	Dialog.confirm("Are you sure to sync categories from sites?").then(
		(confirmed) => {
			if (confirmed) {
				Spinner.activate();
				http.post("admin/news-sites/sync-categories", { id: setting.id })
					.then((response) => {
						const data = response.data.data;
						if (data.tags_count) {
							Notify.success(
								`A background task is running to sync ${data.tags_count} categories.`,
								"Success"
							);
						}
					})
					.catch((error) => {
						const responseData = error.response.data;
						if (responseData.message) {
							Notify.error(responseData.message, "Error!");
						}
					})
					.finally(() => {
						Spinner.deactivate();
					});
			}
		}
	);
};

const getSiteSettingName = (setting: SiteSettingInterface, index: number) => {
	if (setting.site_url.length) {
		return `Site ${index + 1}: ${setting.site_url}`;
	}
	return `Site ${index + 1}`;
};

const getItems = () => {
	crud.getItems().then((data) => {
		state.settings = data.items as SiteSettingInterface[];
	});
};

onMounted(() => {
	getItems();
});
</script>
