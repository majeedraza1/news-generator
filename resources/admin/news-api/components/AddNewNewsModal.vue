<template>
  <form @submit.prevent="addNewNews" autocomplete="off">
    <ShaplaModal :active="active" title="Add New News" @close="emitClose">
      <ShaplaTabs alignment="center">
        <ShaplaTab name="General" selected>
          <div class="mb-4">
            <label for="news_title">News Title</label>
            <input type="text" id="news_title" v-model="state.news_title" class="w-full py-2">
            <p class="text-xs mx-0 mt-1 mb-0 text-gray-500">Minimum 3 words required.</p>
          </div>
          <div class="mb-2">
            <label for="news_content">News Content</label>
            <textarea id="news_content" v-model="state.news_content" class="w-full" rows="8"></textarea>
            <p class="text-xs mx-0 mt-1 mb-0 text-gray-500">Recommended words 300 or more. Minimum 100 words
              required.</p>
          </div>
          <div class="mb-2">
            <label for="news_category">News Category</label><br>
            <select id="news_category" v-model="state.news_category">
              <option v-for="(cat_label,cat_val) in categories" :value="cat_val">{{ cat_label }}</option>
            </select>
            <p class="text-xs mx-0 mt-1 mb-0 text-gray-500">Choose news category.</p>
          </div>
          <div class="mb-2">
            <label for="news_thumbnail">News Image</label>
            <p class="text-xs mx-0 mt-1 mb-2 text-gray-500">Recommended but optional.</p>
            <ShaplaFeaturedImage
                :image-url="extraState.image_url"
                @click:add="OpenNewsImageModal"
                @click:clear="clearNewsImageInfo"
            />
          </div>
        </ShaplaTab>
        <ShaplaTab name="Instagram">
          <div class="mb-4">
            <input type="checkbox" id="use_for_instagram" v-model="state.use_for_instagram">
            <label for="use_for_instagram">Use this news for instagram feed</label>
          </div>
          <div class="mb-4">
            <label for="instagram_heading">Instagram Heading</label>
            <textarea id="instagram_heading" v-model="state.instagram_heading" class="w-full py-2" rows="2"></textarea>
          </div>
          <div class="mb-4">
            <label for="instagram_subheading">Instagram Subheading</label>
            <textarea id="instagram_subheading" v-model="state.instagram_subheading" class="w-full py-2"
                      rows="2"></textarea>
          </div>
          <div class="mb-4">
            <label for="instagram_body">Instagram Body</label>
            <textarea id="instagram_body" v-model="state.instagram_body" class="w-full py-2" rows="3"></textarea>
          </div>
          <div class="mb-4">
            <label for="instagram_hashtag">Instagram Hashtag</label>
            <textarea id="instagram_hashtag" v-model="state.instagram_hashtag" class="w-full py-2" rows="2"></textarea>
          </div>
          <div class="mb-2">
            <label for="instagram_image">News Image for instagram</label>
            <p class="text-xs mx-0 mt-1 mb-2 text-gray-500">If you set this image, it will be used to generate instagram
              feed image</p>
            <ShaplaFeaturedImage
                :image-url="extraState.instagram_image_url"
                @click:add="openInstagramImageModal"
                @click:clear="clearInstagramImageInfo"
            />
          </div>
        </ShaplaTab>
        <ShaplaTab name="Extra Media">
          <div class="mb-2">
            <label for="extra_images">Extra Images</label>
            <p class="text-xs mx-0 mt-1 mb-2 text-gray-500">If you set this images, it will be used to generate feed
              image. To select multiple images, press and hold Ctrl/Cmd and select images.</p>
            <ShaplaFeaturedImage
                v-if="state.extra_images.length < 1"
                @click:add="openExtraImagesModal"
            />
            <div v-if="state.extra_images.length" class="flex flex-wrap space-x-2">
              <div v-for="image in extraState.extra_images" class="shapla-featured-image__thumbnail">
                <ShaplaImage container-width="150px" container-height="150px">
                  <img :src="image" alt=""/>
                </ShaplaImage>
                <ShaplaCross title="Remove Image" @click="clearExtraImage(image)"/>
              </div>
            </div>
          </div>
          <div class="mb-2">
            <label for="extra_videos">Extra Videos</label>
            <p class="text-xs mx-0 mt-1 mb-0 text-gray-500">If you set this videos, it will be used to generate feed
              videos. To select multiple videos, press and hold Ctrl/Cmd and select videos.</p>
            <ShaplaFeaturedImage
                v-if="state.extra_videos.length < 1"
                @click:add="openExtraVideosModal"
            />
            <div v-if="state.extra_videos.length" class="flex flex-wrap space-x-2">
              <div v-for="video in extraState.extra_videos" class="shapla-featured-image__thumbnail">
                <ShaplaImage container-width="150px" container-height="150px">
                  <img :src="video" alt=""/>
                </ShaplaImage>
                <ShaplaCross title="Remove Image" @click="clearExtraVideo(video)"/>
              </div>
            </div>
          </div>
        </ShaplaTab>
        <ShaplaTab name="OpenAI Setting">
          <div class="mb-4">
            <input type="checkbox" id="sync_with_openai" v-model="state.sync_with_openai">
            <label for="sync_with_openai">Sync other fields with openAI</label>
          </div>
        </ShaplaTab>
      </ShaplaTabs>
      <template v-slot:foot>
        <ShaplaButton theme="primary">Submit</ShaplaButton>
      </template>
    </ShaplaModal>
  </form>
</template>

<script setup lang="ts">
import {
  Dialog,
  Notify,
  ShaplaButton,
  ShaplaCross,
  ShaplaFeaturedImage,
  ShaplaImage,
  ShaplaModal,
  ShaplaTab,
  ShaplaTabs,
  Spinner
} from "@shapla/vue-components";
import http from "../../../utils/axios";
import {PropType, reactive} from "vue";
import wpMediaUploader, {
  MultiMediaUploaderResponseInterface,
  SingleMediaUploaderResponseInterface
} from "../../../utils/wpMediaUploader";

defineProps({
  active: {type: Boolean, default: false},
  categories: {type: Object as PropType<Record<string, string>>, default: () => ({})},
})

const state = reactive({
  news_title: '',
  news_content: '',
  news_category: '',
  image_id: 0,
  extra_images: [],
  extra_videos: [],
  use_for_instagram: false,
  instagram_heading: '',
  instagram_subheading: '',
  instagram_body: '',
  instagram_hashtag: '',
  instagram_image_id: 0,
  sync_with_openai: false,
})

const extraState = reactive({
  image_url: '',
  instagram_image_url: '',
  extra_images: [],
  extra_videos: [],
})

const emit = defineEmits(['submit', 'close']);

const emitClose = () => {
  emit('close');
}

const addNewNews = () => {
  Spinner.activate();
  http
      .post(`openai/news`, state)
      .then((response) => {
        Notify.success('News has been re-created from OpenAI.', 'Success');
        emit('submit', response.data.data);
      })
      .catch(error => {
        const responseData = error.response.data;
        if (responseData.message) {
          Notify.error(responseData.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.deactivate();
      })
}

const OpenNewsImageModal = () => {
  wpMediaUploader().then((data: SingleMediaUploaderResponseInterface) => {
    state.image_id = data.id;
    extraState.image_url = data.url;
  })
}

const openInstagramImageModal = () => {
  wpMediaUploader().then((data: SingleMediaUploaderResponseInterface) => {
    state.instagram_image_id = data.id;
    extraState.instagram_image_url = data.url;
  })
}

const openExtraImagesModal = () => {
  wpMediaUploader({multiple: true}).then((data: MultiMediaUploaderResponseInterface) => {
    state.extra_images = data.ids;
    extraState.extra_images = data.urls;
  })
}

const openExtraVideosModal = () => {
  wpMediaUploader({
    multiple: true,
    type: 'video',
    title: 'Add Videos',
    buttonText: 'Use videos',
  }).then((data: MultiMediaUploaderResponseInterface) => {
    state.extra_videos = data.ids;
    extraState.extra_videos = data.urls;
  })
}
const clearNewsImageInfo = () => {
  Dialog.confirm('Are you sure to remove it').then(confirmed => {
    if (confirmed) {
      extraState.image_url = '';
      state.image_id = 0;
    }
  })
}
const clearInstagramImageInfo = () => {
  Dialog.confirm('Are you sure to remove it').then(confirmed => {
    if (confirmed) {
      extraState.instagram_image_url = '';
      state.instagram_image_id = 0;
    }
  })
}

const clearExtraImage = (url: string) => {
  Dialog.confirm('Are you sure to remove it').then(confirmed => {
    if (confirmed) {
      const index = extraState.extra_images.indexOf(url);
      if (-1 !== index) {
        state.extra_images.splice(index, 1);
        extraState.extra_images.splice(index, 1);
      }
    }
  })
}
const clearExtraVideo = (url: string) => {
  Dialog.confirm('Are you sure to remove it').then(confirmed => {
    if (confirmed) {
      const index = extraState.extra_videos.indexOf(url);
      if (-1 !== index) {
        state.extra_videos.splice(index, 1);
        extraState.extra_videos.splice(index, 1);
      }
    }
  })
}
</script>