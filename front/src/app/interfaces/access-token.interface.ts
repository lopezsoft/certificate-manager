import {Users} from "../models/users-model";

export interface  Company {
  uuid            : string;
  company_name    : string;
  email           : string;
  has_agreement   : boolean;
  company_type_id : number;
  issuance_provider: string | null;
}

export interface AccessToken {
  access_token  : string;
  expires_at    : string;
  success       : boolean;
  token_type    : string;
  company       : Company;
  user          : Users;
}
