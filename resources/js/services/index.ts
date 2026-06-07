import { MeService } from './MeService';
import { IcoLookupService } from './IcoLookupService';

/** Singleton service instances — import from here wherever HTTP calls are needed. */
export const meService = new MeService();
export const icoLookupService = new IcoLookupService();
