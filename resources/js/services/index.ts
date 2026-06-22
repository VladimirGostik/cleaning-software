import { MeService } from './MeService';
import { IcoLookupService } from './IcoLookupService';
import { NotificationBellService } from './NotificationBellService';

/** Singleton service instances — import from here wherever HTTP calls are needed. */
export const meService = new MeService();
export const icoLookupService = new IcoLookupService();
export const notificationBellService = new NotificationBellService();
