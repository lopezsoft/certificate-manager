import {Component, EventEmitter, Input, Output} from '@angular/core';
import {MessagesService} from '../../../utils';
import {AuthenticationService} from '../../../services/users';
import {TranslateService} from '@ngx-translate/core';
import {LoadMaskService} from "../../../services/load-mask.service";

@Component({
  selector: 'app-actions-toolbar',
  templateUrl: './actions-toolbar.component.html',
  styleUrls: ['./actions-toolbar.component.scss']
})
export class ActionsToolbarComponent {
  @Output() onEdit = new EventEmitter();
  @Output() onDelete = new EventEmitter();
  @Input() editTooltip = 'Editar';
  @Input() deleteTooltip = 'Eliminar';
  @Input() showEdit = true;
  @Input() showDelete = true;
  constructor(
    public mask: LoadMaskService,
    public msg: MessagesService,
    public auth: AuthenticationService,
    public translate: TranslateService,
  ) { }

  protected isAccess(): boolean {
    return true;
  }

  protected deleteAction() {
    const ts    = this;
    const lang  = ts.translate;
    ts.msg.confirm(lang.instant('titleMessages.delete'), lang.instant('bodyMessages.delete'))
      .then((result) => {
        if (result.isConfirmed) {
          this.onDelete.emit();
        }
      });
  }

  editAction() {
    this.onEdit.emit();
  }
}
