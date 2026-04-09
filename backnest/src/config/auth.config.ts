import { registerAs } from '@nestjs/config';

export default registerAs('auth', () => ({
  /**
   * Configuración de tokens OAuth Bearer (compatible Laravel Passport).
   * El token es opaco (almacenado en oauth_access_tokens), no un JWT.
   * `expiresIn` define los días de vigencia del token.
   */
  token: {
    expiresInDays: parseInt(process.env.TOKEN_EXPIRES_IN_DAYS ?? '90', 10),
  },
  /**
   * @deprecated — Mantenido por retrocompatibilidad con auth.service.ts.
   * Se lee `auth.jwt.expiresIn` para parsear los días de expiración.
   */
  jwt: {
    secret: process.env.JWT_SECRET ?? 'not-used-with-oauth-tokens',
    expiresIn: process.env.JWT_EXPIRES_IN ?? '90d',
  },
}));
