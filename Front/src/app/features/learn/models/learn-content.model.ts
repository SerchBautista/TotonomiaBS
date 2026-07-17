export interface LearnTopicIcon {
  web: string;
  mobile: string;
}

export interface LearnTopicSection {
  title: string;
  body: string;
}

export interface LearnHubContent {
  eyebrow: string;
  title: string;
  lead: string;
  topicsTitle: string;
  featuresTitle?: string;
  featuresLead?: string;
  overviewTitle?: string;
  overviewLead?: string;
  overviewMedia?: LearnFeatureMedia;
}

export interface LearnCtaContent {
  title: string;
  subtitle: string;
  primary: string;
  secondary: string;
}

export interface LearnTopicSummary {
  id: string;
  slug: string;
  icon: LearnTopicIcon;
  title: string;
  summary: string;
  eyebrow: string;
}

export interface LearnTopicDetail extends LearnTopicSummary {
  lead: string;
  disclaimer: string | null;
  sections: LearnTopicSection[];
}

export interface LearnFeatureMedia {
  poster: string;
  webm: string;
  mp4: string;
}

export interface LearnFeatureSummary {
  id: string;
  slug: string;
  icon: LearnTopicIcon;
  title: string;
  summary: string;
  eyebrow: string;
}

export interface LearnFeatureShowcase extends LearnFeatureSummary {
  lead: string;
  sections: LearnTopicSection[];
  screenshot: string;
}

export interface LearnFeatureDetail extends LearnFeatureSummary {
  lead: string;
  sections: LearnTopicSection[];
  media: LearnFeatureMedia;
}

export interface LearnCatalog {
  version: number;
  updatedAt: string;
  locale: string;
  hub: LearnHubContent;
  cta: LearnCtaContent;
  topics: LearnTopicSummary[];
  features: LearnFeatureShowcase[];
}

export interface LearnCatalogResponse {
  data: LearnCatalog;
}

export interface LearnTopicResponse {
  data: LearnTopicDetail;
}

export interface LearnFeatureResponse {
  data: LearnFeatureDetail;
}
