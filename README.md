# AI Review Test

PR'lar açıldığında Claude'un otomatik kod incelemesi yaptığı demo repo.

## Nasıl Çalışıyor?

1. Bir PR açıldığında veya güncellendiğinde GitHub Actions tetiklenir.
2. [Claude Code Action](https://github.com/anthropics/claude-code-action) repo'yu checkout eder.
3. Claude, `CLAUDE.md` dosyasındaki kuralları okur.
4. Değişen kodu repo bağlamında inceler ve PR'a yorum bırakır.

## Kurulum

### 1. Anthropic API Key

[console.anthropic.com](https://console.anthropic.com) üzerinden bir API key oluştur.

### 2. GitHub Secret Ekle

Repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**:

- **Name:** `ANTHROPIC_API_KEY`
- **Value:** _(API key'in)_

### 3. Test Et

Yeni bir branch aç, küçük bir değişiklik yap, PR aç. Birkaç saniye içinde Claude yorum bırakacak.

## Dosya Yapısı

```
.
├── CLAUDE.md                              # AI'nın uyacağı kurallar
├── .github/
│   └── workflows/
│       └── claude-code-review.yml         # PR review workflow
└── README.md
```

## Özelleştirme

- **Kuralları değiştirmek için:** `CLAUDE.md` dosyasını düzenle.
- **Workflow davranışını değiştirmek için:** `.github/workflows/claude-code-review.yml` içindeki `prompt` bloğunu düzenle.
- **`@claude` ile manuel çağırmak için:** Bir PR yorumunda `@claude şu konuya bakar mısın?` yaz.

## Maliyet

Her PR review ~birkaç cent ila birkaç dolar arası (PR boyutuna göre). API kullanımını [console.anthropic.com](https://console.anthropic.com) üzerinden takip edebilirsin.
