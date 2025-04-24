// prettier-ignore
export interface CoreConfig {

  app: {
    appName: string;
    appTitle: string;
    appLogoImage: string;
    appLogoHome: string;
  };
  layout: {
    skin: 'default' | 'bordered' | 'dark' | 'semi-dark' | 'green';
    type: 'vertical' | 'horizontal';
    menu: {
      hidden: boolean;
      collapsed: boolean;
    };
    navbar: {
      hidden: boolean;
      type: 'navbar-static-top' | 'fixed-top' | 'floating-nav' | 'd-none';
      background: 'navbar-dark' | 'navbar-light';
      customBackgroundColor: boolean;
      backgroundColor: string;
    };
    footer: {
      hidden: boolean;
      type: 'footer-static' | 'footer-sticky' | 'd-none';
      background: 'footer-dark' | 'footer-light';
      customBackgroundColor: boolean;
      backgroundColor: string;
    };
    enableLocalStorage: boolean;
    customizer: boolean;
    scrollTop: boolean;
    buyNow: boolean;
  };
}
