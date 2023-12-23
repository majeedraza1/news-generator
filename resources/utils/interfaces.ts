interface LocationInterfaces {
  label: { eng: string }
  type: string
  wikiUri: string
  lat?: number;
  long?: number
}

interface ConceptInterfaces {
  label: { eng: string }
  uri: string
  type: string
  score?: string
}

interface CategoryInterface {
  uri: string;
  label: string;
  parentUri: string;
}

interface SourceInterface {
  uri: string;
  title: string;
  dataType?: string;
  score?: number;
}

interface NewsSyncQueryInfoInterface {
  get: {
    url: string;
    method: string;
    timeout: number;
    sslverify: boolean;
    headers: Record<string, string>
  };
  post: {
    url: string;
    method: string;
    timeout: number;
    sslverify: boolean;
    headers: Record<string, string>
    body: Record<string, string>
  };
}

interface NewsSyncSettingsInterface {
  option_id: string;
  locations: LocationInterfaces[];
  categories: CategoryInterface[];
  concepts: ConceptInterfaces[];
  sources: SourceInterface[];
  sourceUri: string[];
  locationUri: string[];
  categoryUri: string[];
  conceptUri: string[];
  lang: string[];
  keyword?: string;
  keywordLoc?: string;
  last_sync?: string;
  primary_category?: string;
  copy_news_image?: boolean;
  enable_news_filtering: boolean;
  enable_live_news: boolean;
  news_filtering_instruction: string;
  query_info?: NewsSyncQueryInfoInterface;
  fields?: string[];
}

interface OpenAiSettingsInterface {
  api_key: string;
  organization: string;
  limit_per_day: number;
  request_sent?: number
}

interface NewsToSiteLogInterface {
  id: number;
  remote_site_url: string;
  remote_news_url: string;
  created_at: string;
}

interface OpenAiNewsInterface {
  id: number;
  source_id?: number;
  title?: string;
  content?: string;
  meta_description?: string;
  facebook_text?: string;
  tweet?: string;
  tags?: string[];
  image_id?: number;
  image?: { id: number; url: string; width: number; height: number; };
  category?: { name: string; slug: string };
  openai_category?: { name: string; slug: string };
  sync_status?: string;
  concept?: string;
  created?: string;
  updated?: string;
  country?: {
    in_title: boolean;
    code: string;
    name: string;
  };
  remote_log?: NewsToSiteLogInterface[];
  created_via?: string;
  sync_setting_id?: string;
  sync_setting?: Record<string, string>;
}

interface NewsTagInterface {
  id: number;
  name: string;
  slug: string;
  description?: string;
  meta_description?: string;
}

interface ArticleInterface {
  id: number;
  uri: string;
  data_type: string;
  lang: string;
  news_source_url: string;
  title: string;
  body: string;
  source_title: string;
  source_uri: string;
  source_data_type: string;
  image: string
  event_uri?: string
  sim: number;
  sentiment: number;
  location: string;
  category: string;
  country: string;
  news_datetime: string;
  openai_news_id: number;
  openai_news?: OpenAiNewsInterface;
  sync_settings?: Record<string, any>;
}

interface SiteSettingInterface {
  id?: number;
  site_url: string;
  auth_credentials: string;
  auth_username: string;
  auth_password: string;
  auth_type: "Basic" | "Bearer" | "Post-Params" | "Get-Args" | "Append-to-Url" | "None";
  auth_post_params?: string;
  auth_get_args?: string;
  last_sync_datetime?: string;
  sync_settings?: Record<string, string>[];
  created_at?: string;
  updated_at?: string;
}

interface StatusInterface {
  key: string;
  label: string;
  count: number;
  active: boolean;
}

interface NewsSourceInterface {
  id: number;
  title: string;
  uri: string;
  data_type: string;
  copy_image: boolean | 0 | 1;
  in_whitelist: boolean | 0 | 1;
  in_blacklist: boolean | 0 | 1;
}

interface InterestingNewsFilterInterface {
  id: number;
  primary_category: string;
  raw_news_ids: number[];
  news_ids_for_suggestion: number[];
  suggested_news_ids: number[];
  openai_api_instruction: string;
  openai_api_response: string;
  total_suggested_news: number;
  total_recreated_news: number;
  sync_settings: Record<string, string | string[]>;
  created_at: string;
  updated_at: string;
}

interface OpenAiResponseInterface {
  id: number;
  model: string;
  group: string;
  response_type: 'success' | 'error';
  source_type: string;
  source_id: number;
  total_time: number;
  total_tokens: number;
  created_at: string;
  instruction: string;
  api_response: string;
  debug_url: string;
}

interface InstagramAttemptLogInterface {
  id: number;
  type: 'success' | 'error';
  message: string;
  suggestion: null | number[];
  created_at: string;
  updated_at: string;
}


interface KeywordInterface {
  keyword: string;
  instruction: string;
}

interface ExistingKeywordInterface extends KeywordInterface {
  id?: number
}

interface NewsApiResponseLogInterface {
  id: number;
  existing_records_ids: number[];
  new_records_ids: number[];
  news_articles: Record<string, any>[];
  total_pages: number;
  sync_setting_id: string;
  created_at: string;
  updated_at: string;
}

export type {
  LocationInterfaces,
  ConceptInterfaces,
  NewsSyncSettingsInterface,
  CategoryInterface,
  ArticleInterface,
  OpenAiSettingsInterface,
  OpenAiNewsInterface,
  SourceInterface,
  SiteSettingInterface,
  StatusInterface,
  NewsSyncQueryInfoInterface,
  NewsTagInterface,
  NewsSourceInterface,
  InterestingNewsFilterInterface,
  OpenAiResponseInterface,
  NewsToSiteLogInterface,
  InstagramAttemptLogInterface,
  KeywordInterface,
  ExistingKeywordInterface,
  NewsApiResponseLogInterface
}
