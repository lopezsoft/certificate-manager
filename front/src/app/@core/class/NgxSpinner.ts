import { NgxSpinnerService } from 'ngx-spinner';

export default class NgxSpinner {
  static show(spinner: NgxSpinnerService): void {
    if (spinner) {
      spinner.show(undefined,
        {
          type: 'ball-triangle-path',
          size: 'medium',
          bdColor: 'rgba(0, 0, 0, 0.8)',
          color: '#fff',
          fullScreen: true
        });
    }
  }

  static hide(spinner: NgxSpinnerService): void {
    if (spinner) {
      spinner.hide();
    }
  }
}
