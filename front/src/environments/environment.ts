// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  hmr: false,
  production: false,
  SOCKET_URL: '',
  APIJWT: 'cm-app-jwt',
  APPURL: 'http://cm-api.test',
  APIURL: 'http://cm-api.test/api/v1',
  VERSION: '1.2.4',
  config: {
    name: 'MANAGER',
    title: 'CERTIFICATE MANAGER - Aplicación para la gestión de solicitudes de certificados de firma digital',
    logo: 'assets/img/logo-empresa-32.png',
    logoHome: 'assets/img/logo-empresa-32.png',
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
