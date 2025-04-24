import * as moment from 'moment-timezone';

export class DateManager {
	public static currentDateM(timeZone = 'America/Bogota'): moment.Moment {
		return moment().tz(timeZone);
	}
	public static currentDate(timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).format('YYYY-MM-DD');
	}
	public static localDate(timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).format('DD-MM-YYYY');
	}
	public static oldDate(days = 90, timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).subtract(days, 'days').format('YYYY-MM-DD');
	}
	public static addDays(date: string, days: number, timeZone = 'America/Bogota'): string {
		return moment(date).tz(timeZone).add(days, 'days').format('YYYY-MM-DD');
	}
	public static firstDayOfMonth(timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).startOf('month').format('YYYY-MM-DD');
	}
	
	public static currentTime(timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).format('HH:mm:ss');
	}
	
	public static currentDateTime(timeZone = 'America/Bogota'): string {
		return moment().tz(timeZone).format('DD-MM-YYYY HH:mm:ss A');
	}
}
