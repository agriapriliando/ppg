# Synthetic media fixtures

Generated locally using FFmpeg color sources and an optional sine tone. These files contain no participant data or third-party recordings.

- `h264-aac.mp4`: blue 320x180, one second, H.264/AAC-LC, faststart metadata.
- `h264-portrait.mp4`: green 180x320, one second, H.264, no audio, metadata at end.
- `h264-long.mp4`: blue 64x64 at 1 fps, twelve minutes, H.264, no audio; proves absence of a duration limit without a large fixture.
- `hevc.mp4`: red 64x64, one second, HEVC; must be rejected before upload.
- `audio-only.mp4`: one-second AAC tone; must be rejected before upload.
- `encrypted.mp4`: synthetic H.264/AAC source encrypted with FFmpeg CENC; must be rejected. Test-only all-zero key and all-ones KID, no secrets or real content.

The tests do not require FFmpeg at runtime. Fixtures can be regenerated with `ffmpeg -f lavfi -i color=...` and `-c:v libx264` or `libx265`; the audio fixtures use `-f lavfi -i sine=... -c:a aac`.

`sample.mov` dan `sample.webm` adalah fixture sintetis turunan dari `h264-portrait.mp4` untuk memeriksa dukungan format.
