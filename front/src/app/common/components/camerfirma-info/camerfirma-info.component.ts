import { Component, inject, Input } from '@angular/core';
import { IssuanceProviderService } from 'app/services/issuance-provider.service';

@Component({
  selector: 'app-camerfirma-info',
  templateUrl: './camerfirma-info.component.html',
  styleUrl: './camerfirma-info.component.scss',
  standalone: false
})
export class CamerfirmaInfoComponent {
  @Input() public pin: string;

  protected issuanceProvider: IssuanceProviderService = inject(IssuanceProviderService);
}
