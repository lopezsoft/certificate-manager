// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  hmr: false,
  production: false,
  isSandbox: true,
  SOCKET_URL: '',
  APIJWT: 'maticerts-app-jwt',
  APPURL: 'http://cm-api.test',
  APIURL: 'http://cm-api.test/api/v1',
  WOMPI_PUBLIC_KEY: 'pub_test_q4gEVnZWpzfEROZMScHdZgH4ChcGHW2E',
  VERSION: '1.9.1',
  config: {
    name: 'MATICERTS',
    title: 'MATICERTS - Aplicación para la gestión de solicitudes de certificados de firma digital',
    logo: 'assets/images/logo/logo-horizontal-blue.png',
    logoHome: 'assets/images/logo/logo-circle-blue.png',
    skin: 'default', // default, dark, bordered, semi-dark, green
    type: 'vertical', // vertical, horizontal
  }
};

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
