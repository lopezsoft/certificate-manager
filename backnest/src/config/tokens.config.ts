import { registerAs } from '@nestjs/config';

export default registerAs('tokens', () => ({
  expirationDays: parseInt(process.env.TOKEN_EXPIRATION_DAYS ?? '90', 10),
  maxExpirationDays: parseInt(process.env.TOKEN_MAX_EXPIRATION_DAYS ?? '365', 10),
  maxPerDay: parseInt(process.env.TOKEN_MAX_PER_DAY ?? '10', 10),
  maxActive: parseInt(process.env.TOKEN_MAX_ACTIVE ?? '20', 10),
}));
