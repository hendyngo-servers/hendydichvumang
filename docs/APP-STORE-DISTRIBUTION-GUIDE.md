# HENDY App Store — upload, distribution and installation operations

## Architecture

- Public HTML/CSS/JavaScript lives in \`website/public/app-store/\`.
- The public catalog reads only released packages from \`GET /api/v1/store/releases\`.
- The PHP 8.3 backend is the single authenticated service boundary.
- Installer binaries are stored in the private Neon Object Storage namespace \`store-releases/{productId}/...\`; users receive a short-lived signed download URL only after Admin publication.
- Admin release controls run inside the existing Telegram-authenticated Admin dashboard and use the Admin allowlist.
- Source-code ZIPs use a separate metadata record (\`storeSourceArchive\`) and private Admin-only download endpoint. They are not public catalog entries, installers or executable server tasks.

No new table, schema migration, second product database or alternate hosting provider is introduced. Existing \`metadata.storeRelease\` records remain supported as the compatibility pointer while new records are stored independently in \`metadata.storeReleases[platform]\`.

## Admin release workflow

1. Create or select the product in **Dịch vụ & sản phẩm**.
2. Complete Supplier Readiness: source permission must be authorized, supplier access verified and product sellable.
3. In **App Store · Release Studio**, select the product and target platform.
4. Optionally upload a source ZIP (maximum 100 MB). It is stored privately, hashed with SHA-256 and made available only to authenticated Admins using a five-minute signed link. HENDY does not unzip, execute, compile or publish this archive.
5. Build the actual installer in the platform's supported build environment. Upload the resulting installer binary (maximum 200 MB) using the platform-specific upload form.
6. Enter version, channel, architecture, package/bundle identifier, minimum OS, publisher label, official store URL and installation notes as appropriate.
7. Inspect the draft, file type, checksum, signature and platform distribution permissions. The upload checks extension, MIME and basic file signature; these checks do **not** replace malware scanning, code-signature verification, notarization, device compatibility or publisher verification.
8. Publish only the platform variant that has passed review. Hiding one platform does not hide other platforms for the same product. Revoking Supplier Readiness automatically hides every published variant.

The browser/API never receives private Neon credentials or source ZIP download URLs in the public catalog.

## Supported installer formats

| Platform | Accepted installer format(s) | User experience |
|---|---|---|
| Android | APK | Download and open APK; Android may ask for permission to install from that source |
| Windows | EXE, MSI, MSIX, ZIP | Download and run the installer; MSIX must be signed and trusted |
| iOS / iPadOS | IPA | Prefer App Store / TestFlight; OTA is offered only when Admin explicitly attests to the signed IPA and valid provisioning/distribution setup |
| macOS | DMG, PKG, ZIP | Download and install after macOS verifies the publisher/signature |
| Linux | AppImage, DEB, RPM, TAR.GZ | Follow package-specific installation steps |
| Web | ZIP | Extract or host according to the product's documented instructions |

A format being accepted by the upload endpoint means only that the basic file signature matches. It does not certify the installer as safe or ready for public use.

## Platform-specific publishing requirements

### Android APK

Android APK packages must be signed with a cryptographic certificate before they can be installed or updated. Keep the signing key private, retain the original key for future updates, and verify the installed package identity before publication. Users installing directly from the website may need to opt in to installing unknown apps for that specific source; the storefront should not instruct users to disable Play Protect globally.

Official references:
- [Prepare an Android app for release](https://developer.android.com/studio/publish/preparing)
- [Sign your Android app](https://developer.android.com/studio/publish/app-signing)
- [Alternative distribution and install-unknown-apps behavior](https://developer.android.com/distribute/marketing-tools/alternative-distribution)

### Windows EXE / MSI / MSIX

For public distribution, sign installers with a publisher identity users can verify. MSIX packages require a valid code-signing certificate trusted by the target Windows device. Direct MSIX distribution can use an \`.appinstaller\` feed for update delivery, but the package still requires trusted signing. Microsoft's Store may re-sign supported package submissions after certification; this is separate from direct download from HENDY.

Official references:
- [Choose a Windows app distribution path](https://learn.microsoft.com/en-us/windows/apps/package-and-deploy/choose-distribution-path)
- [MSIX package signing overview](https://learn.microsoft.com/en-us/windows/msix/package/signing-package-overview)
- [Microsoft Store signing FAQ](https://learn.microsoft.com/en-us/windows/apps/publish/faq/get-started-with-the-microsoft-store)

### iOS / iPadOS IPA

An IPA is not a universal tap-to-install package. Distribution must use an allowed Apple method:
- App Store Connect / TestFlight with the appropriate Apple Developer Program distribution provisioning profile.
- Ad Hoc distribution only for devices registered in the developer account and included in the provisioning profile.
- Enterprise distribution only for eligible organization-internal deployment with the correct Enterprise provisioning profile; MDM is recommended for organizational deployment.

HENDY exposes App Store/TestFlight links when configured. Direct OTA uses \`itms-services\` only after Admin confirms the IPA signature and the applicable provisioning/distribution setup. Merely entering a manifest URL is not enough. If neither a verified OTA route nor a store listing exists, users may download the IPA file but must follow the publisher's valid device-specific installation route.

Official references:
- [Apple Xcode distribution methods](https://help.apple.com/xcode/mac/current/en.lproj/dev31de635e5.html)
- [Distribute to registered development devices](https://developer.apple.com/documentation/xcode/distributing-apps-to-your-development-devices)
- [Enterprise app distribution](https://help.apple.com/xcode/mac/current/en.lproj/devba5e7054d.html)

## API contracts

Public, unauthenticated, read-only:
- \`GET /api/v1/store/releases\`
- \`GET /api/v1/store/releases/check?appId={slug-or-id}&currentVersion={version}&platform={platform}\`

The older \`/api/public/releases\` and \`/api/public/releases/check\` endpoints remain compatibility aliases.

Admin-only, authenticated with the existing Telegram Admin session:
- \`POST /api/admin/products/{id}/release-upload\` — installer binary draft.
- \`PATCH /api/admin/products/{id}\` — publish/hide and configure a specific platform variant using \`storeReleasePlatform\`.
- \`POST /api/admin/products/{id}/source-upload\` — private source ZIP.
- \`GET /api/admin/products/{id}/source-download\` — short-lived private source ZIP link.

The source download route sits behind the existing Admin router and audit logging. The source archive never appears in \`/api/v1/store/releases\`.

## Security and operational notes

- Upload binary: 16 bytes–200 MB; source ZIP: 16 bytes–100 MB.
- Upload validates filename/extension, MIME, basic magic bytes and SHA-256.
- Public release download links are signed for a short duration and generated from private Neon objects.
- Official marketplace links are allow-listed to Apple App Store, TestFlight, Google Play and Microsoft Store hosts.
- Public responses contain only the published platform variant and allow-listed fields. Private supplier data, source ZIP metadata, user data, balances and orders are never serialized into the public App Store response.
- The API client uses HTTPS, no cookies, no browser cache, an abort timeout and strict backend host allow-listing.
- No private signing certificate, PFX, keystore, provisioning profile or signing password should be uploaded into the public storefront or committed to Git.
- A green CI run is not proof that a binary is correctly signed, malware-free, notarized or installable on every target device.
