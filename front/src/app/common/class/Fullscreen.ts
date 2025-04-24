

export class Fullscreen {

	toggleFullScreen() {
		if (!document.fullscreenElement) {
			this.enterFullScreen();
		} else {
			if (document.exitFullscreen) {
				document.exitFullscreen().then(() => {});
			}
		}
	}

	private enterFullScreen() {
		const elem = document.documentElement as any;
		if (elem.requestFullscreen) {
			elem.requestFullscreen().then(() => {});
		} else if (elem.msRequestFullscreen) { /* IE11 */
			elem.msRequestFullscreen();
		} else if (elem.mozRequestFullScreen) { /* Firefox */
			elem.mozRequestFullScreen();
		} else if (elem.webkitRequestFullscreen) { /* Chrome, Safari & Opera */
			elem.webkitRequestFullscreen();
		}
	}
}
