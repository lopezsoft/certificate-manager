import { DataSource } from 'typeorm';
import { config } from 'dotenv';
import { join } from 'path';

config(); // Load .env

export default new DataSource({
  type: 'postgres',
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '5432', 10),
  username: process.env.DB_USERNAME || 'postgres',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_DATABASE || 'certificate_manager',
  entities: [join(__dirname, 'src/database/entities/**/*.entity.{ts,js}')],
  migrations: [join(__dirname, 'src/database/migrations/**/*.{ts,js}')],
  synchronize: false,
  logging: process.env.NODE_ENV !== 'production',
});
