import { registerAs } from '@nestjs/config';

export default registerAs('auth', () => ({
  jwt: {
    secret: process.env.JWT_SECRET ?? 'change-this-secret-in-production',
    expiresIn: process.env.JWT_EXPIRES_IN ?? '90d',
  },
}));
