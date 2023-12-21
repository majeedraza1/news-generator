import {createApp} from 'vue';
import router from './routers';
import App from './App.vue'
import WordPressMenuFix from "../../utils/WordPressMenuFix";

declare global {
    interface Window {
        StackonetNewsGenerator: {
            restRoot: string;
            restNonce: string;
            ajaxUrl: string;
            countries: Record<string, string>;
            categories: Record<string, string>;
            languages: Record<string, string>;
            instructions: {
                title: string;
                body: string;
                meta: string;
                tweet: string;
                facebook: string;
                tag: string;
            };
        },
        wp:any;
    }
}

let el = document.querySelector('#nusify-news-api-admin');
if (el) {
    const app = createApp(App);
    app.use(router);
    app.mount(el);
}

// fix the admin menu for the slug "stackonet-toolkit"
new WordPressMenuFix('news-api');
