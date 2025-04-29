// chair-position.utils.ts (sin cambios)


import {ChairPosition} from '../../interfaces/chair-position.interface';

export function computeChairPositions(chairCount: number): ChairPosition[] {
	if (chairCount <= 0) { return []; }

	const topCount = Math.ceil(chairCount / 4);
	const bottomCount = Math.ceil(chairCount / 4);
	const remaining = chairCount - (topCount + bottomCount);
	const leftCount = Math.floor(remaining / 2);
	const rightCount = remaining - leftCount;

	const positions: ChairPosition[] = [];

	const createLinearPositions = (count: number, axis: 'top'|'bottom'|'left'|'right') => {
		for (let i = 0; i < count; i++) {
			const offsetPercentage = (100 / (count + 1)) * (i + 1);
			const position: ChairPosition = {};

			if (axis === 'top') {
				position.top = '0%';
				position.left = offsetPercentage + '%';
				position.transform = 'translate(-50%, -50%)';
			} else if (axis === 'bottom') {
				position.bottom = '0%';
				position.left = offsetPercentage + '%';
				position.transform = 'translate(-50%, 50%)';
			} else if (axis === 'left') {
				position.left = '0%';
				position.top = offsetPercentage + '%';
				position.transform = 'translate(-50%, -50%)';
			} else if (axis === 'right') {
				position.right = '0%';
				position.top = offsetPercentage + '%';
				position.transform = 'translate(50%, -50%)';
			}

			positions.push(position);
		}
	};

	createLinearPositions(topCount, 'top');
	createLinearPositions(bottomCount, 'bottom');
	createLinearPositions(leftCount, 'left');
	createLinearPositions(rightCount, 'right');

	return positions;
}