# Stackonet News Generator

A WordPress plugin to get news from NewsApi(newsapi.ai) and rewrite with OpenAi(openai.com) and distribute via webhook.

## Todo

- [x] Use OpenAI to make the actual news articles beautiful.
- [x] Add a naver copy image option.
- [ ] Same Keyword two settings for actual and not actual. but only actual is working.
    - If you are using same keyword for two settings. We will get same news from api.
    - We have duplicate checking to exclude existing news so the sync settings running later the first one won't work.
    - Try with other different keyword