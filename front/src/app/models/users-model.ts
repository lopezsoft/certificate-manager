import { Role } from "app/auth/models";

export class Users {
  id            : number;
  type_id       : number;
  mail          : string;
  email         : string;
  avatar        : string;
  avatarUrl     : string;
  message       : string;
  name          : string;
  first_name    : string;
  last_name     : string;
  success       : boolean;
  user_type     : UserTypes;
  role?         : Role;
  active        : boolean;
}

export interface UserTypes {
  id: number;
  user_type_name: string;
  type: number;
  active: boolean;
}
