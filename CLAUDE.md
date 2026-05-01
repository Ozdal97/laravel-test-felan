# Kod İnceleme Standartları (CLAUDE.md)

> Bu dokümana göre AI kod inceleyici (Claude) her PR'ı değerlendirir.
> Standartlar **SOLID, Clean Code, OWASP** prensiplerine göre düzenlenmiştir.
> Her ihlal seviyesine göre işaretlenir: **🚨 BLOCKER**, **⚠️ WARNING**, **💡 SUGGESTION**, **✅ NICE**.

---

## 1. Proje Bağlamı

| Alan | Değer |
|---|---|
| **Framework** | Laravel 11 (PHP 8.2+) |
| **Mimari** | Layered: Controller → Service → Repository → Model |
| **ORM** | Eloquent |
| **Test** | PHPUnit / Pest |
| **Paradigma** | OOP + SOLID + Clean Architecture |

---

## 2. SOLID Prensipleri

### 2.1 Single Responsibility Principle (SRP) — Tek Sorumluluk

> Bir sınıfın **değişmesi için yalnızca tek bir nedeni** olmalıdır.

**❌ İhlal — Service hem iş mantığı hem mail gönderiyor hem log yazıyor:**
```php
class OrderService
{
    public function placeOrder(array $data): Order
    {
        $order = Order::create($data);
        Mail::to($order->user)->send(new OrderPlaced($order));
        Log::info("Order placed: {$order->id}");
        Cache::forget('orders:list');
        return $order;
    }
}
```

**✅ Doğru — Her sorumluluk ayrı sınıfta, event/listener veya orchestration:**
```php
class OrderService
{
    public function placeOrder(array $data): Order
    {
        $order = $this->orders->create($data);
        OrderPlaced::dispatch($order); // listener'lar mail/log/cache halleder
        return $order;
    }
}
```

**Tetikleyiciler:** Sınıfta `+`, `&`, `And` olan metodlar; 5+ farklı facade kullanımı; 200+ satır class.

---

### 2.2 Open/Closed Principle (OCP) — Açık/Kapalı

> Genişlemeye açık, değişikliğe kapalı.

**❌ İhlal — Yeni ödeme tipi eklemek için sınıfı değiştirmek gerekiyor:**
```php
class PaymentProcessor
{
    public function process(string $type, float $amount): bool
    {
        if ($type === 'credit_card') { /* ... */ }
        elseif ($type === 'paypal') { /* ... */ }
        elseif ($type === 'crypto') { /* ... */ }
    }
}
```

**✅ Doğru — Strategy pattern + interface:**
```php
interface PaymentMethodInterface
{
    public function process(float $amount): bool;
}

class PaymentProcessor
{
    public function __construct(private PaymentMethodInterface $method) {}

    public function process(float $amount): bool
    {
        return $this->method->process($amount);
    }
}
```

---

### 2.3 Liskov Substitution Principle (LSP) — Yerine Geçebilirlik

> Alt sınıflar, üst sınıfın yerine sorunsuz kullanılabilmelidir.

**❌ İhlal — alt sınıf farklı davranış sergiliyor:**
```php
class Bird { public function fly(): void { /* ... */ } }
class Penguin extends Bird {
    public function fly(): void {
        throw new Exception('Penguenler uçamaz');
    }
}
```

**✅ Doğru — abstraction farklı:**
```php
interface FlyableInterface { public function fly(): void; }
class Bird {}
class Sparrow extends Bird implements FlyableInterface { /* ... */ }
class Penguin extends Bird {} // FlyableInterface implement etmiyor
```

---

### 2.4 Interface Segregation Principle (ISP) — Arayüz Ayrıştırma

> İstemciyi kullanmadığı arayüzlere bağımlı bırakma.

**❌ İhlal — şişkin interface:**
```php
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function create(array $data): User;
    public function exportToCsv(): string;
    public function generatePdfReport(): Pdf;
    public function syncWithSalesforce(): void;
}
```

**✅ Doğru — küçük, odaklı interface'ler:**
```php
interface UserReaderInterface { public function find(int $id): ?User; }
interface UserWriterInterface { public function create(array $data): User; }
interface UserExporterInterface { public function exportToCsv(): string; }
```

---

### 2.5 Dependency Inversion Principle (DIP) — Bağımlılık Tersine Çevirme

> Üst seviye modüller alt seviye modüllere bağlı olmamalı; ikisi de soyutlamalara bağlı olmalı.

**❌ İhlal — concrete class'a bağımlı:**
```php
class UserService
{
    public function __construct(
        private EloquentUserRepository $users // concrete!
    ) {}
}
```

**✅ Doğru — interface'e bağımlı:**
```php
class UserService
{
    public function __construct(
        private UserRepositoryInterface $users // abstraction!
    ) {}
}
```

> **Kontrol:** Her service constructor'ı **interface** almalı, asla doğrudan repository class'ı değil.

---

## 3. Mimari Katmanlar

### 3.1 Controller (Thin Controller)

**Görev:** HTTP request → service çağırma → response. **İş mantığı YASAK.**

**✅ Doğru:**
```php
class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->register($request->validated());
        return response()->json($user, 201);
    }
}
```

**Yasaklar:**
- Eloquent doğrudan kullanımı (`User::create(...)`)
- `if/else` ile iş mantığı
- Validation `validate()` ile inline (FormRequest kullan)
- `$request->all()` mass assignment için (`validated()` kullan)

---

### 3.2 Service (İş Mantığı Katmanı)

**Görev:** İş kuralları, transaction yönetimi, orchestration.

**Kurallar:**
- Repository'yi **interface** üzerinden DI ile alır
- **Doğrudan Eloquent kullanmaz** (her zaman repository üzerinden)
- Transaction yönetimi burada (`DB::transaction`)
- Mail, event dispatch, cache invalidation burada
- Tek bir domain işine odaklanır

**✅ Doğru:**
```php
class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private InventoryServiceInterface $inventory,
    ) {}

    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $this->inventory->reserve($data['items']);
            $order = $this->orders->create($data);
            OrderPlaced::dispatch($order);
            return $order;
        });
    }
}
```

---

### 3.3 Repository (Veri Erişim Katmanı)

**Görev:** Veritabanı erişimi. **İş mantığı YASAK.**

**Kurallar:**
- Bir interface'i olmalı (`XxxRepositoryInterface`)
- Sadece CRUD ve sorgu metodları
- Eager loading **burada** yapılır (controller/service değil)
- Pagination metodları olmalı
- `select()` ile sadece gerekli kolonlar

**✅ Doğru:**
```php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function paginateActive(int $perPage = 15): LengthAwarePaginator;
    public function getWithPostsAndComments(): Collection;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
    public function delete(User $user): bool;
}
```

**Yasaklar:**
- Mail/event/log işlemleri
- Hash, validation, formatting
- `Auth::user()` kullanımı

---

### 3.4 Model (Domain Entity)

**Kurallar:**
- `$fillable` veya `$guarded` **mutlaka** tanımlı
- Type-hinted relationship metodları
- `casts()` metodu ile cast tanımları (Laravel 11+)
- `protected $hidden` ile hassas alanlar gizlenmeli (`password`, `remember_token`)
- Scope kullanımı teşvik edilir (`scopeActive`, `scopePublished`)
- **İş mantığı yasak** — sadece accessor/mutator/scope/relation

---

## 4. 🚨 KRİTİK: N+1 Query Detection

**N+1, bu projede #1 BLOCKER'dır.** Her PR'da kontrol edilir.

### 4.1 N+1 Pattern'leri

#### Pattern 1: Foreach içinde lazy load
```php
// ❌ N+1 — N user için 1 + N query
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();
}
```

#### Pattern 2: Collection map/each içinde ilişki
```php
// ❌ N+1
$posts->map(fn ($p) => $p->author->name);
$orders->each(fn ($o) => $o->customer->update(...));
```

#### Pattern 3: Blade template'de loop
```blade
{{-- ❌ N+1 --}}
@foreach($users as $user)
    <p>{{ $user->profile->avatar_url }}</p>
@endforeach
```

#### Pattern 4: Nested ilişki (gizli N+1)
```php
// ❌ Çift N+1
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->comments->count(); // her post için yeni query
    }
}
```

#### Pattern 5: Aggregate function loop'ta
```php
// ❌ N+1 sum
$total = 0;
foreach ($orders as $order) {
    $total += $order->items->sum('price');
}
```

### 4.2 Çözümler

| Sorun | Çözüm |
|---|---|
| `$user->posts` loop'ta | `User::with('posts')->get()` |
| Lazy collection sonrası | `$users->load('posts')` |
| Sadece sayı gerekli | `User::withCount('posts')->get()` |
| Lazy count | `$users->loadCount('posts')` |
| Nested ilişki | `User::with('posts.comments')->get()` |
| Sadece belirli kolonlar | `User::with('posts:id,user_id,title')->get()` |
| Conditional eager load | `User::with(['posts' => fn($q) => $q->published()])` |

### 4.3 N+1 Yorumu Formatı

```
🚨 BLOCKER — N+1 Query Detected

Bu döngüde her iterasyonda yeni bir DB sorgusu çalışıyor.
N kullanıcı için 1 + N query oluşur — production'da yavaşlar.

Önerilen çözüm:
$users = User::with('posts')->get();
// veya sadece sayı gerekiyorsa:
$users = User::withCount('posts')->get();

Referans: https://laravel.com/docs/eloquent-relationships#eager-loading
```

---

## 5. Eloquent / Veritabanı Standartları

### 5.1 Sorgu Optimizasyonu

| Kural | Açıklama |
|---|---|
| **`SELECT *` yasak** | `select(['id', 'name'])` ile sadece gereken kolonlar |
| **Pagination zorunlu** | Liste endpoint'lerinde `paginate()`, asla `get()` ile tüm tablo |
| **Index kontrolü** | `where()`, `orderBy()`, `join()` kolonları indexli olmalı |
| **`first()` yerine `firstOrFail()`** | Bulunamayabilirse fail-fast |
| **`exists()` kullan** | Sadece varlık kontrolünde `count() > 0` yerine |
| **Chunk büyük datasette** | `chunk()` veya `lazy()` ile bellek tüket me |

### 5.2 Mass Assignment Güvenliği

```php
// ❌ Yasak
User::create($request->all());

// ✅ Doğru
User::create($request->validated());
// veya
$user->update($request->only(['name', 'email']));
```

### 5.3 Migration Standartları

- `down()` metodu **mutlaka** geri alınabilir olmalı
- Foreign key cascade davranışı açıkça belirtilmeli
- Index'ler migration'da tanımlanmalı
- `softDeletes()` kullanılıyorsa indeks de eklenmeli

---

## 6. Güvenlik (OWASP Top 10)

### 6.1 SQL Injection
```php
// ❌ Vulnerable
User::whereRaw("email = '{$email}'")->first();

// ✅ Safe — binding
User::whereRaw('email = ?', [$email])->first();
// veya zaten Eloquent
User::where('email', $email)->first();
```

### 6.2 XSS (Cross-Site Scripting)
```blade
{{-- ❌ Tehlikeli — kullanıcı verisinde --}}
{!! $user->bio !!}

{{-- ✅ Güvenli — auto-escape --}}
{{ $user->bio }}
```

### 6.3 CSRF
- Tüm POST/PUT/PATCH/DELETE form'larında `@csrf` zorunlu
- API endpoint'lerinde Sanctum/Passport token kontrolü

### 6.4 Mass Assignment
- `$fillable` veya `$guarded` zorunlu
- `$request->all()` asla mass assignment'ta kullanılmaz

### 6.5 Authorization
```php
// ✅ Policy/Gate kontrolü
$this->authorize('update', $post);
// veya
abort_unless(Gate::allows('view', $post), 403);
```

- Her sensitive endpoint'te policy/gate kontrolü olmalı
- `Auth::user()->id === $resource->user_id` gibi manuel kontroller policy'ye taşınmalı

### 6.6 Secret Management
- `.env` **asla** commit edilmemeli (`.gitignore`'da olmalı)
- Hard-coded API key, password, token **YASAK**
- Secret'lar `config()` üzerinden okunmalı, doğrudan `env()` ile değil

### 6.7 Rate Limiting
```php
Route::middleware('throttle:60,1')->group(/* ... */);
```
- Public endpoint'lerde rate limit zorunlu
- Login endpoint'inde sıkı limit (`throttle:5,1`)

### 6.8 Şifre Güvenliği
- `Hash::make()` kullanımı zorunlu, asla plain text saklanmaz
- Min 8 karakter (validation'da)
- `password_confirmation` ile çift kontrol

---

## 7. Clean Code Prensipleri

### 7.1 İsimlendirme
- **Class:** `PascalCase` (`UserService`)
- **Method/değişken:** `camelCase` (`getUserById`)
- **Constant:** `SCREAMING_SNAKE_CASE`
- **Boolean:** `is`, `has`, `can` prefix'i (`isActive`, `hasPermission`)
- **İsimler niyeti açıklamalı:** `$d` ❌, `$daysSinceCreation` ✅
- **Magic number yasak** — `const` veya `config` kullan

### 7.2 Method Kuralları
- **Maksimum 50 satır** (ideal: 20)
- **Maksimum 4 parametre** (üzerinde DTO/array kullan)
- **Tek seviye soyutlama** (method içinde mixed level abstraction kötüdür)
- **Cyclomatic complexity ≤ 10**
- **Early return** kullan, deep nesting'den kaçın

```php
// ❌ Deep nesting
public function process($user) {
    if ($user) {
        if ($user->isActive()) {
            if ($user->hasPermission()) {
                // ...
            }
        }
    }
}

// ✅ Early return
public function process($user) {
    if (!$user || !$user->isActive() || !$user->hasPermission()) {
        return;
    }
    // ...
}
```

### 7.3 Yorumlar
- **NEDEN** yazılır, **NE** yapıldığı değil (kod zaten anlatır)
- Outdated yorum bug'dan kötüdür — silinir
- TODO/FIXME varsa issue numarası eklenmeli

### 7.4 Type Safety
- `declare(strict_types=1);` öneri
- **Tüm parametre ve return type'lar yazılmalı**
- `mixed` kullanımı son çare
- Nullable türleri `?Type` ile belirt
- Generic için PHPDoc kullan: `@return Collection<int, User>`

---

## 8. Test Standartları

### 8.1 Coverage Beklentisi
- **Service katmanı:** ≥ %80 coverage
- **Repository:** ≥ %70
- **Controller:** Feature test ile happy + edge path

### 8.2 Test Türleri
| Tür | Ne için? |
|---|---|
| **Unit** | Service iş mantığı, isolated, mock'lu |
| **Feature** | HTTP endpoint, DB ile, real flow |
| **Integration** | External service entegrasyonu |

### 8.3 Test Yapısı (AAA)
```php
public function test_user_can_be_registered(): void
{
    // Arrange
    $data = ['name' => 'Ahmet', 'email' => 'a@a.com', 'password' => 'secret123'];

    // Act
    $user = $this->service->register($data);

    // Assert
    $this->assertInstanceOf(User::class, $user);
    $this->assertNotSame('secret123', $user->password);
}
```

### 8.4 Yeni PR Kontrolü
- Yeni public method → en az 1 unit test
- Yeni endpoint → en az 1 feature test
- Bug fix → regression test (aynı bug'ı yakalar)
- N+1 düzeltmesi → query count assertion (`DB::listen` veya `assertQueryCountLessThan`)

---

## 9. Performans Kuralları

### 9.1 Genel
- **N+1 yasak** (Bölüm 4)
- **Cache aware ol:** Tekrarlanan hesaplamalar `Cache::remember()` ile
- **Queue kullan:** Mail, notification, dış API çağrısı sync olmamalı
- **Big data ile chunk:** `Model::chunk(1000, fn ($rows) => /* ... */)`

### 9.2 Frontend (varsa)
- N+1 sayılan veriler API response'unda hazır gelmeli
- Pagination meta bilgisi response'da olmalı
- Image optimization, lazy loading

---

## 10. Git / PR Standartları

### 10.1 Branch İsimlendirme
- `feature/user-export`
- `fix/n-plus-one-on-dashboard`
- `refactor/extract-payment-service`
- `chore/update-deps`

### 10.2 Commit Mesajları (Conventional Commits)
```
feat: add user CSV export endpoint
fix: resolve N+1 in dashboard stats
refactor: extract OrderService.place() into smaller methods
test: add coverage for PaymentProcessor
docs: update CLAUDE.md with SOLID examples
```

### 10.3 PR Kuralları
- **Tek bir konu** (feature, fix, refactor — karıştırma)
- **PR boyutu ≤ 400 satır** (ideal)
- Açıklamada: ne, neden, nasıl test edildi
- Breaking change varsa açıkça belirt
- İlişkili issue link'i

---

## 11. Logging & Observability

- **Log seviyesi doğru kullan:** `debug`, `info`, `warning`, `error`, `critical`
- **Log'a hassas veri yazma** (password, token, credit card)
- **Structured log:** `Log::info('User registered', ['user_id' => $user->id])`
- Exception'lar `report()` veya custom handler ile yakalanmalı

---

## 12. İnceleme Tonu ve İşaretleme

### Seviyeler

| Etiket | Anlam | Örnek |
|---|---|---|
| 🚨 **BLOCKER** | Merge edilmemeli | N+1, SQL injection, mimari ihlal, eksik validation |
| ⚠️ **WARNING** | Düzeltilmesi şiddetle önerilir | Code smell, performans riski, test eksikliği |
| 💡 **SUGGESTION** | İyileştirme önerisi | Refactor, daha temiz isim, alternatif yaklaşım |
| ✅ **NICE** | Övgüye değer | İyi yazılmış kısımlar — pozitif geri bildirim |

### Yorum Stili

- **Yapıcı ve saygılı** ol — geliştiriciye değer kat
- **"Bu yanlış" yerine "Şu sebepten X yaklaşımı daha iyi"** de
- **Çözüm öner** — sadece sorun gösterme
- **Kısa ve net** ol — gereksiz uzatma
- **Kod örneği ekle** — soyut açıklama yerine somut

### PR Sonu Özeti

Her PR'a şu formatta genel özet bırak:

```markdown
## 📋 İnceleme Özeti

**Genel Durum:** ✅ Onay / ⚠️ Düzeltme Gerekli / 🚨 Block

| Kategori | Durum |
|---|---|
| SOLID | ✅ / ⚠️ / 🚨 |
| N+1 | ✅ / 🚨 |
| Güvenlik | ✅ / ⚠️ / 🚨 |
| Test | ✅ / ⚠️ |
| Clean Code | ✅ / ⚠️ |

**Bulgu Sayısı:** X BLOCKER, Y WARNING, Z SUGGESTION

**Öncelikli İşlem:** _(en kritik 1-2 madde)_
```

---

## 13. Görmezden Gelinecek Konular

- `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/` içeriği
- Auto-generated migration timestamp'leri
- IDE config dosyaları (`.idea/`, `.vscode/`)
- `composer.lock` değişiklikleri (sadece güvenlik açığı varsa belirt)
- Stil tercihleri (linter zaten halleder — Pint/PHP-CS-Fixer)

---

## 14. Referanslar

- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Clean Code by Robert C. Martin](https://www.amazon.com/Clean-Code-Handbook-Software-Craftsmanship/dp/0132350884)
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)

---

> **Not:** Bu doküman canlı bir şekilde geliştirilir. Yeni pattern'ler, hatalar veya
> deneyimler eklendiğinde güncellenir. Her ekibin geri bildirimi değerlidir.
