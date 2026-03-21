// src/types/index.ts

// Kiểu dữ liệu cho Slide Số liệu chuyên sâu
export interface MatchInsight {
  _id?: string;
  homeTeam: string;
  awayTeam: string;
  matchTime: string; // Định dạng "HH:mm - DD/MM"
  hot: boolean;
  insights: string[];
  prediction: string;
  publishedAt?: string;
}

// Kiểu dữ liệu cho hình ảnh trong Sanity
export interface SanityImage {
  _type: "image";
  asset: {
    _ref: string;
    _type: "reference";
  };
  alt?: string;
  hotspot?: {
    x: number;
    y: number;
    height: number;
    width: number;
  };
  crop?: {
    top: number;
    bottom: number;
    left: number;
    right: number;
  };
}

// Kiểu dữ liệu cho Slug
export interface Slug {
  _type: "slug";
  current: string;
}

// Kiểu dữ liệu cho Category/Danh mục
export interface Category {
  _id?: string;
  _type?: "category";
  title: string;
  slug: Slug;
  description?: string;
}

// Kiểu dữ liệu cho nội dung Portable Text
export interface PortableTextBlock {
  _type: string;
  _key: string;
  [key: string]: any;
}

// Kiểu dữ liệu cơ bản cho Bài viết
export interface Post {
  _id?: string;
  _type?: "post";
  _createdAt?: string;
  _updatedAt?: string;
  title: string;
  slug: Slug;
  excerpt?: string;
  mainImage?: SanityImage;
  content?: PortableTextBlock[];
  publishedAt?: string;
  category?: Category;
  hashtags?: string[];
  author?: {
    name: string;
    image?: SanityImage;
  };
}

// Kiểu dữ liệu cho Bài viết trong danh sách (query đơn giản)
export interface PostListItem {
  _id?: string;
  title: string;
  slug: {
    current: string;
  };
  excerpt?: string;
  mainImage?: SanityImage;
  publishedAt?: string;
  categoryName?: string;
  categorySlug?: string;
}

// Kiểu dữ liệu cho Bài viết chi tiết (trang single)
export interface PostDetail {
  _id?: string;
  title: string;
  slug: {
    current: string;
  };
  excerpt?: string;
  mainImage?: SanityImage;
  content?: PortableTextBlock[];
  publishedAt?: string;
  categoryName?: string;
  categorySlug?: string;
  tags?: string[];
  author?: {
    name: string;
    image?: SanityImage;
  };
}

// Kiểu dữ liệu cho Bài viết trong Slider
export interface SliderPost {
  title: string;
  slug: string;
  mainImage?: SanityImage;
  excerpt?: string;
  category?: string;
  categoryName?: string;
  categorySlug?: string;
}
