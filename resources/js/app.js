import 'bootstrap/js/dist/collapse';
import { publicClaimForm } from './public-claim-form';
import { fileUploadQueue } from './file-upload-queue';

window.publicClaimForm = publicClaimForm;
window.fileUploadQueue = fileUploadQueue;

document.addEventListener('livewire:navigating', () => {
    document.body.style.overflow = '';
});
