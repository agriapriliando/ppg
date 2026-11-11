export function fileExtension(name) {
    const index = name.lastIndexOf('.');
    return index > 0 ? name.slice(index + 1).toLowerCase() : '';
}

export function validateFile(file, rules, reservedBytes = 0, reservedVideos = 0) {
    const extension = fileExtension(file.name);
    if (!file.size) throw new Error('File kosong. Pilih berkas yang berisi dokumen.');
    if (!rules.allowed_extensions.includes(extension)) {
        throw new Error(`Format tidak didukung. Gunakan ${rules.allowed_extensions.join(', ').toUpperCase()}.`);
    }
    if (file.size > rules.max_file_size_bytes) {
        throw new Error(`Ukuran ${(file.size / 1048576).toFixed(2)} MiB melebihi batas ${(rules.max_file_size_bytes / 1048576).toFixed(2)} MiB per berkas.`);
    }
    if (rules.remaining_bytes !== null && file.size + reservedBytes > rules.remaining_bytes) {
        throw new Error('Sisa kapasitas tidak cukup setelah berkas sebelumnya dalam antrean. Kurangi ukuran atau keluarkan berkas lain.');
    }
    if (rules.video_extensions.includes(extension) && rules.available_video_slots !== null && reservedVideos >= rules.available_video_slots) {
        throw new Error('Batas jumlah video pada workspace ini sudah tercapai.');
    }
    // Browser MIME may be empty or incorrect. Actual MIME is checked by the server.
    return extension;
}
