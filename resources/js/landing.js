// Public landing page: the same interaction layer the app uses (pointer
// spotlight on cards), scoped to the landing's own card components.
import { bindSpotlight } from './ui-enhancements';

const LANDING_CARDS = [
    '.feature-card', '.pillar-card', '.role-card', '.mission-card',
    '.contact-method', '.trust-item', '.hws-item',
].join(',');

bindSpotlight(LANDING_CARDS);
