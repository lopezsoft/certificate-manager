import { registerAs } from '@nestjs/config';

export default registerAs('certificate', () => ({
  adminEmail: process.env.CERTIFICATE_ADMIN_EMAIL ?? 'soporte@matias.com.co',
  notificationDays: parseInt(process.env.CERTIFICATE_NOTIFICATION_DAYS ?? '30', 10),
  dailyNotificationsEnabled: process.env.CERTIFICATE_DAILY_NOTIFICATIONS_ENABLED !== 'false',
  urgencyLevels: {
    critical: 7,
    high: 15,
    medium: 30,
  },
  schedule: {
    notificationsTime: '0 8 * * *',      // 08:00 AM diario
    dailyReportTime: '0 7 * * *',        // 07:00 AM diario
    weeklyReportTime: '0 9 * * 1',       // Lunes 09:00 AM
    monthlyCompanyTime: '0 22 28-31 * *', // Fin de mes 22:00
    monthlyAdminTime: '0 23 28-31 * *',   // Fin de mes 23:00
    timezone: 'America/Bogota',
  },
  queues: {
    notifications: 'notifications',
    reports: 'reports',
  },
  cache: {
    notificationTtl: 86400, // 24h
  },
}));
