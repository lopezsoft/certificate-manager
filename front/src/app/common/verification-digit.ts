

export class VerificationDigit {
	public static getDigit(Number: number = 0): number {
		let result: number;
		const vector: number[] = [];

		if (Number > 0) {
			const SValue: string = Number.toString();
			let Accumulator = 0;
			vector[1] = 3;
			vector[2] = 7;
			vector[3] = 13;
			vector[4] = 17;
			vector[5] = 19;
			vector[6] = 23;
			vector[7] = 29;
			vector[8] = 37;
			vector[9] = 41;
			vector[10] = 43;
			vector[11] = 47;
			vector[12] = 53;
			vector[13] = 59;
			vector[14] = 67;
			vector[15] = 71;

			const NValue: number = SValue.length;
			for (let i = 0; i < NValue; i++) {
				// tslint:disable-next-line:radix
				const Num: number = parseInt(SValue.charAt(i));
				Accumulator += Num * vector[NValue - i];
			}

			const Residue: number = Accumulator % 11;
			result = (Residue > 1) ? 11 - Residue : Residue;
		} else {
			result = -1;
		}

		return result;
	}
}