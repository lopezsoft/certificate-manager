import {Users} from "../models/users-model";

export interface  Company {
  company_name: string;
  email: string;
  id: number;
  image: string;
  trade_name: string;
}

export interface AccessToken {
  access_token  : string;
  expires_at    : string;
  success       : boolean;
  token_type    : string;
  company       : Company[];
  user          : Users;
}
