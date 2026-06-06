import { MeService } from './MeService';

/** Singleton service instances — import `meService` wherever capabilities HTTP is needed. */
export const meService = new MeService();
