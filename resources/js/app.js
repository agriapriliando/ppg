import 'bootstrap/js/dist/collapse';
import { publicClaimForm } from './public-claim-form';

window.publicClaimForm = publicClaimForm;

document.addEventListener('livewire:navigating', () => {
    document.body.style.overflow = '';
});
