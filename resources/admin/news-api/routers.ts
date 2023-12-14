import {createRouter, createWebHashHistory} from 'vue-router';
import Home from './pages/Home.vue';
import Settings from './pages/Settings.vue';
import Sync from './pages/Sync.vue';
import Sites from './pages/Sites.vue';
import ReCreatedNews from './pages/ReCreatedNews.vue';
import NewsTags from './pages/NewsTags.vue';
import NewsSources from './pages/NewsSources.vue';
import NewsFiltering from './pages/NewsFiltering.vue';
import Tweets from './pages/Tweets.vue';
import OpenAiLogs from './pages/OpenAiLogs.vue';
import ExternalLinksList from './pages/ExternalLinksList.vue';
import Logs from './pages/Logs.vue';

const routes = [
  {path: '/', name: 'ReCreatedNews', component: ReCreatedNews},
  {path: '/news', name: 'Home', component: Home},
  {path: '/tags', name: 'tags', component: NewsTags},
  {path: '/sources', name: 'sources', component: NewsSources},
  {path: '/news-filtering', name: 'filtering', component: NewsFiltering},
  {path: '/sync', name: 'Sync', component: Sync},
  {path: '/sites', name: 'Sites', component: Sites},
  {path: '/tweets', name: 'Tweets', component: Tweets},
  {path: '/external-links', name: 'ExternalLinks', component: ExternalLinksList},
  {path: '/settings', name: 'Settings', component: Settings},
  {path: '/openai-logs', name: 'OpenAiLogs', component: OpenAiLogs},
  {path: '/logs', name: 'Logs', component: Logs},
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

export default router;

