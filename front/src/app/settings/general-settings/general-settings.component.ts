import {Component, OnInit} from '@angular/core';
import {SettingsService} from '../../services/settings.service';
import {SettingEntry} from '../../models/general-model';
import {Router} from '@angular/router';
import {LoadMaskService} from "../../services/load-mask.service";

@Component({
  selector: 'app-general-settings',
  templateUrl: './general-settings.component.html',
  styleUrls: ['./general-settings.component.scss']
})
export class GeneralSettingsComponent implements OnInit {
  constructor(
    public _settings: SettingsService,
    private route: Router,
    private mask: LoadMaskService,
  ) { }

  ngOnInit(): void {
    this._settings.getSettings();
  }

  protected getListValues(values: string): any[] {
    const list = values.split('|');
    return list.map((item: string) => {
      return item.trim();
    });
  }

  protected getNumberValues(values: string): number {
    return parseInt(values.trim());
  }

  saveAndClose() {
    const settingValues = [];
    this._settings.settings.forEach((setting: SettingEntry) => {
      settingValues.push({
        id: setting.id,
        value: setting.value
      });
    });
    this.mask.showBlockUI('Guardando ajustes');
    this._settings.saveSettings(settingValues)
      .subscribe({
          next: () => {
            this._settings.getSettings();
            this.mask.hideBlockUI();
            this.route.navigate(['/settings']);
          },
          error: () => this.mask.hideBlockUI(),
      });
  }

  cancel() {
    this.route.navigate(['/settings']);
  }

  onChange(setting: SettingEntry, $event: number) {
    setting.value = $event.toString();
  }

  switchChange(setting: SettingEntry, $event: Event) {
    const checked = $event.target['checked'];
     setting.value  = checked ? '1' : '0';
  }
  isSwitch(setting: SettingEntry): boolean {
     return parseInt(setting.value.trim()) === 1;
  }
}
