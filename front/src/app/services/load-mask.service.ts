import {TranslateService} from '@ngx-translate/core';

import {Injectable} from '@angular/core';
import {BlockUI, NgBlockUI} from 'ng-block-ui';

@Injectable({ providedIn: 'root' })
export class LoadMaskService {
    @BlockUI() blockUI: NgBlockUI;
    constructor(
        public translate: TranslateService,
    ) {
        this.maskSpinner    = 'Realizando petición...';
        this.translate      = translate;
    }
    public maskSpinner: string;
    public showSpinner(mask: string = ''): void {
        const ts = this;
        if (mask.length > 0) {
            ts.maskSpinner = mask;
        } else {
            ts.maskSpinner = ts.translate.instant('messages.loading');
        }
        this.showBlockUI(ts.maskSpinner);
    }

    public hideSpinner(): void {
        this.hideBlockUI();
    }
    /*
    * Block UI Message
    * */
    public showBlockUI(message?: string) {
        this.blockUI.start(message);
    }

    public hideBlockUI() {
        this.blockUI.stop();
    }
}
