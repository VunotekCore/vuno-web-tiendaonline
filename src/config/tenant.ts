export interface TenantConfig {
  brand: {
    name: string
    slogan: string
    description: string
    url: string
    logo: string
  }
  seo: {
    defaultTitle: string
    defaultDescription: string
    ogDefaultImage: string
    twitterSite: string
    themeColor: string
    googleSiteVerification: string
    bingSiteVerification: string
    robotsDefault: string
  }
  analytics: {
    gaId: string
  }
  social: {
    instagram: string
    facebook: string
    twitter: string
    pinterest: string
    urls: string[]
  }
}

export const tenant: TenantConfig = {
  brand: {
    name: 'Vunotek',
    slogan: 'Architectural Minimalism in Footwear',
    description: 'Calzado artesanal para damas con diseño minimalista arquitectónico.',
    url: 'https://shop.anicasolucionesintegrales.com',
    logo: '',
  },
  seo: {
    defaultTitle: 'Vunotek | Calzado Artesanal para Damas',
    defaultDescription: 'Calzado artesanal para damas con diseño minimalista arquitectónico. Descubrí nuestra colección de calzado hecho a mano.',
    ogDefaultImage: '/og-default.jpg',
    twitterSite: '@vunotek',
    themeColor: '#1A1A1A',
    googleSiteVerification: '',
    bingSiteVerification: '',
    robotsDefault: 'index, follow',
  },
  analytics: {
    gaId: '',
  },
  social: {
    instagram: '',
    facebook: '',
    twitter: '',
    pinterest: '',
    urls: [],
  },
}
