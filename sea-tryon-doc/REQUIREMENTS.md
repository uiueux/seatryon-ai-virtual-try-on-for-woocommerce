# WooCommerce AI Virtual Try-On — Requirements Specification

> Status: Draft v0.2  
> Date: 2026-08-08  
> Working plugin name: **Virtual Try-On**  
> Plugin slug: `sea-tryon`; text domain: `seatryon-ai-virtual-try-on-for-woocommerce`
> Document language: Chinese; all default plugin UI strings and source strings are English.

## 1. 文档目的

本文定义一个 WooCommerce AI 试穿/试戴与商品场景融合扩展的首个可发布版本（MVP）。扩展在单个产品页面提供 **Virtual Try-On** 入口，允许顾客上传本人照片或房间/场景图片，并将上传图片与当前产品图片及商家配置的提示词一起发送至 AI 图像服务，最终向顾客展示生成效果图。

本文是产品与技术需求基线。OpenAI GPT Image 2 与 SeaAI Universal X 的主要 contract 已定义；SeaAI 异步 query contract、测试凭证和第 18.2 节剩余产品决策确认后再冻结为 v1.0。

## 2. 背景与产品价值

服装、饰品、眼镜、假发等商品在线购买时，顾客难以判断商品在自己身上的视觉效果；家具和一般商品也难以预览其在目标空间中的融合效果。扩展通过生成式 AI 提供低门槛的虚拟试穿/试戴与场景融合体验，并为商家提供可控、可配置、符合 WordPress/WooCommerce 规范的集成方式。

## 3. 产品目标

### 3.1 MVP 目标

1. 商家可全局启用或禁用虚拟试穿功能，并安全配置 AI 服务。
2. 商家可对单个产品启用功能并配置产品专属 Prompt。
3. 顾客可在产品页上传照片、提交生成、查看并下载结果。
4. 商家可分别配置登录用户和访客的每日生成次数，并决定访客是否必须登录后使用。
5. 扩展不会将 API Key 暴露给浏览器，也不会长期保留顾客照片。
6. 管理端与前端默认使用英文，并具备完整国际化能力。
7. 插件满足 WordPress Coding Standards、WooCommerce 扩展规范、隐私要求与 WCAG 2.2 AA 可访问性目标。

### 3.2 建议衡量指标

- Try-on modal open rate：看到按钮的产品页会话中，打开弹窗的比例。
- Upload-to-submit rate：打开弹窗后成功提交生成的比例。
- Generation success rate：已发送至 Provider 的任务中成功生成结果的比例。
- Median / P95 generation time：生成耗时中位数与 P95。
- Try-on assisted add-to-cart rate：生成成功后同一会话内的加购比例。

统计埋点仅限站内聚合数据；MVP 不向插件开发者发送遥测。

## 4. 用户角色

### 4.1 商家管理员

- 在 OpenAI GPT Image 2 与 SeaAI Universal X 中二选一，并配置对应 API Key、使用限额和调试日志。
- 对产品启用/禁用试穿并编写产品 Prompt。
- 查看成功生成总数并重置统计。
- 查看经过脱敏的 WooCommerce 日志。

### 4.2 顾客

- 在产品页打开试穿弹窗。
- 阅读照片要求与隐私说明。
- 上传、预览、更换个人照片。
- 发起生成，查看进度、错误和结果。
- 下载结果或重新生成。

## 5. MVP 范围

### 5.1 P0（首发必须）

- WooCommerce 设置页。
- 产品级启用开关和 Prompt。
- 单个产品页的 **Virtual Try-On** 按钮。
- 响应式、键盘可操作的模态框。
- JPEG、PNG、WebP 照片上传与预览。
- 当前产品图 + 顾客/场景图 + 产品 Prompt 的服务端 AI 请求。
- 生成状态、结果展示、下载和重试。
- 访客访问开关，以及登录用户/访客两套可独立设置的每日限额。
- 成功生成总数与重置操作。
- WooCommerce 日志集成和敏感信息脱敏。
- 临时文件自动清理、隐私说明和明确同意。
- 简单产品和可变产品；可变产品优先使用当前已选 Variation 图片。
- 服装、饰品、眼镜、假发、家具及一般商品场景融合。

### 5.2 P1（首发后优先）

- 产品 Variation 级启用、图片和 Prompt 覆盖。
- HEIC/HEIF 输入转换。
- 商家自定义按钮文字、位置和基础样式。
- 多个输出候选图。
- 顾客登录后的历史结果页。
- 用量按日/产品/Provider 的管理报表。
- 自动 API 连通性测试和诊断工具。

### 5.3 明确不在 MVP 范围

- 实时 AR、摄像头视频试戴或 3D 模型。
- 视频生成。
- AI 服务订阅、代充值或商家计费系统。
- 社交分享、公共图库或社区内容。
- 自动将生成结果保存到 WordPress Media Library。
- 多 Provider 自动故障转移。
- 插件开发者侧遥测或 SaaS 控制台。

## 6. 核心用户流程

### 6.1 商家首次设置

1. 商家安装并激活插件。
2. 若 WooCommerce 未启用，插件不加载业务模块，并显示可关闭的管理通知。
3. 商家进入 **WooCommerce > Settings > Products > Virtual Try-On**。
4. 商家在 **OpenAI GPT Image 2** 与 **SeaAI Universal X** 中选择一个 Provider，填写对应 API Key，并配置访客权限和两类每日限额。
5. 系统校验字段格式；保存后 API Key 只显示掩码，不回显完整值。
6. 商家编辑产品，启用 **Virtual Try-On** 并填写产品 Prompt。

### 6.2 顾客试穿/试戴

1. 顾客进入已启用功能的产品页。
2. 页面在购买区域显示 **Virtual Try-On** 按钮。
3. 顾客点击按钮，模态框打开并获得键盘焦点。
4. 顾客阅读图片要求、AI 结果免责声明和第三方处理说明。
5. 顾客上传照片；系统在客户端预检，并在服务端再次校验。
6. 顾客勾选同意项后点击 **Generate Try-On**。
7. 系统校验产品状态、授权令牌、配额和文件，再建立生成任务。
8. UI 显示生成进度；顾客可关闭弹窗，当前页面内重新打开后继续查询任务。
9. 成功后显示结果图以及 **Download**, **Try Again**, **Close**。
10. 失败时显示可操作的英文错误信息；仅可重试的错误提供重试操作。

## 7. 功能需求

### 7.1 全局设置

设置位置遵循参考图：**WooCommerce > Settings > Products > Virtual Try-On**。优先使用 WooCommerce Settings API 和正式公开接口，不建立品牌顶级菜单。

| ID | Requirement | Default |
| --- | --- | --- |
| ADM-001 | **Enable Virtual Try-On**：全局开关。关闭时不输出前端按钮、不加载前端资源、不接受新任务。 | Off |
| ADM-002 | **AI Provider**：二选一：`OpenAI GPT Image 2` 或 `SeaAI Universal X`。同一任务只调用选中的 Provider。 | OpenAI GPT Image 2 |
| ADM-003 | **OpenAI API Key**：仅在选择 OpenAI 时显示；密码字段；用于 OpenAI Image API。 | Empty |
| ADM-004 | **SeaAI Base URL** 与 **SeaAI API Key**：仅在选择 SeaAI 时显示；Base URL 指向 `/wp-json/seaai/v1` 根路径，Key 为 SeaAI 用户 Key，不是上游 Universal X Provider Key。 | Base URL: `https://theminitech.net/wp-json/seaai/v1`; API Key: Empty |
| ADM-005 | **Allow Guest Try-On**：开启后访客无需登录即可生成；关闭后访客点击按钮时显示登录提示，服务端拒绝匿名生成。 | Off |
| ADM-006 | **Daily Limit for Logged-in Users**：每个非管理员登录用户每个自然日允许派发至 Provider 的任务数；范围 1–100。拥有 `manage_options` 权限的 WordPress 管理员不受次数限制。 | 3 |
| ADM-007 | **Daily Limit for Guests**：每个匿名 session 每个自然日允许派发至 Provider 的任务数；仅在允许访客时启用；范围 1–100。 | 3 |
| ADM-008 | **OpenAI Quality**：`auto`, `low`, `medium`, `high`；遵循 GPT Image 2 规范。 | auto |
| ADM-009 | **SeaAI Quality**：`low`, `medium`, `high`。 | low |
| ADM-010 | **Debug Mode**：显式 opt-in；使用 `WC_Logger`，日志 source 为 `sea-tryon`。 | Off |
| ADM-011 | **Usage Statistics**：显示成功生成总数。 | 0 |
| ADM-012 | **Reset Statistics**：要求 capability、nonce 和二次确认；仅重置聚合统计，不删除配置。 | — |
| ADM-013 | 设置保存使用 `manage_woocommerce` 权限、WooCommerce nonce 和字段级 sanitize/validate。 | Required |
| ADM-014 | API Key option 设置为不自动加载（autoload off）；允许通过常量或 filter 注入 Key，以便生产环境不落库。 | Required |

### 7.2 产品设置

产品字段位于 WooCommerce Classic Product Editor 的产品数据 **Advanced** 区域。WooCommerce 已在 10.9 弃用 block-based Product Editor Beta，并在 11.0 将其移除，因此插件不得依赖 `@woocommerce/product-editor`、`product-block-editor-v1` 或相关实验性接口。

| ID | Requirement |
| --- | --- |
| PRD-001 | **Enable Virtual Try-On**：产品级开关，默认关闭。 |
| PRD-002 | **Virtual Try-On Prompt**：可选；为空时仅使用 Experience Type 对应的系统模板；填写时作为产品专属要求追加；纯文本；去除脚本和 HTML；上限 2,000 字符。 |
| PRD-003 | 产品必须存在可读取的产品图，否则禁止启用或在保存时显示明确错误。 |
| PRD-004 | 保存字段必须校验 `edit_post` / `edit_product` 能力及 WooCommerce/WordPress nonce。 |
| PRD-005 | 简单产品使用主图；可变产品在顾客选择有效 Variation 后优先使用 Variation 图，否则回退至父产品主图。 |
| PRD-006 | 前端不允许顾客编辑最终 Prompt；产品 Prompt 只由有权编辑产品的商家维护。 |
| PRD-007 | **Experience Type**：`Auto`, `Clothing`, `Earrings`, `Rings`, `Necklaces`, `Bracelets`, `Nose Rings`, `Belly Button Rings`, `Hair Accessories`, `Anklets`, `Brooches & Pins`, `Lip Rings`, `Tongue Rings`, `Body Chains`, `Glasses`, `Wig`, `Furniture`, `Product Placement`；默认 `Auto`。用于选择受控 Prompt 模板和上传说明。历史 `jewelry` 值仅保留运行时兼容，不再作为后台新选项。 |

建议 Prompt 示例（默认英文）：

> Place the selected product naturally on the person in the uploaded image. Preserve the person's identity, pose, body proportions, background, and lighting. Keep the product's color, shape, material, and visible details accurate.

家具/商品场景融合的建议 Prompt：

> Place the selected product naturally into the uploaded room or scene. Preserve the scene layout, perspective, architecture, background, and lighting. Keep the product's color, shape, material, scale, and visible details accurate.

不同 Experience Type 在 Provider 层使用受控模板，但不得覆盖商家产品 Prompt 的明确要求。`Auto` 可依据产品分类选择模板；无法可靠判断时使用通用商品融合模板。

### 7.3 产品页显示规则

仅当以下条件全部满足时显示按钮：

1. WooCommerce 已加载且版本受支持。
2. 全局开关开启。
3. 当前产品启用了 Virtual Try-On。
4. Provider 配置完整。
5. 当前产品/Variation 存在有效图片。
6. 当前页面是可购买产品的单个产品页面。

前端资源只在满足基本显示条件的产品页加载。默认按钮文字为 **Virtual Try-On**，位置靠近购买区域，并提供 action/filter 供主题或二次开发调整位置、文案和可见性。

当 **Allow Guest Try-On** 关闭时，未登录访客仍可看到按钮以理解商品具备此功能；点击后显示 **Please log in to use Virtual Try-On.** 和站内登录链接，不显示上传控件，也不得创建任务。

### 7.4 上传与图片校验

| ID | Requirement |
| --- | --- |
| IMG-001 | 支持 MIME：`image/jpeg`, `image/png`, `image/webp`；禁止 SVG、GIF 和伪造扩展名。 |
| IMG-002 | 默认最大文件 10 MB；最终值不得超过 WordPress、PHP 和 Provider 中的最小限制。 |
| IMG-003 | 建议最小尺寸 512 × 512 px，最大处理尺寸 4096 × 4096 px；超大图片在保持比例和 EXIF 方向正确的情况下缩放。 |
| IMG-004 | 同时进行浏览器预检和服务端权威校验，包括 `wp_check_filetype_and_ext()`、实际 MIME、可解码性、尺寸和文件大小。 |
| IMG-005 | 上传后显示预览、文件名、更换和删除操作；不把顾客图加入 Media Library。 |
| IMG-006 | 产品图由服务端读取，不信任前端提交的任意产品图 URL。 |
| IMG-007 | 临时文件使用高熵随机文件名，不使用原始文件名作为磁盘路径或公开 URL。 |

### 7.5 模态框和结果体验

| ID | Requirement |
| --- | --- |
| UI-001 | 模态框包含标题、与 Experience Type 匹配的图片说明、上传区、预览区、隐私同意、生成按钮、状态区和结果区。人物模式要求上传本人照片；家具/商品融合模式要求上传房间或目标场景照片。 |
| UI-002 | 生成中禁用重复提交，并显示非阻塞进度状态。 |
| UI-003 | 状态变化使用 `aria-live` 宣告；生成过程不得只用颜色表达状态。 |
| UI-004 | 成功时显示一张结果图，提供有意义的 alt 文本与下载按钮。 |
| UI-005 | **Try Again** 创建新任务并计入限额；不会覆盖当前结果，直至新任务成功。 |
| UI-006 | 显示免责声明：AI-generated previews may be inaccurate and do not guarantee fit, size, color, or appearance. |
| UI-007 | 响应式支持 320 px 宽度；200% 和 400% 浏览器缩放时关键内容与操作不可丢失。 |

建议默认英文文案：

- Modal title: **Virtual Try-On**
- Person-mode upload label: **Upload your photo**
- Person-mode helper: **Use a clear, well-lit photo. Keep your face and the relevant body area visible.**
- Scene-mode upload label: **Upload your room or scene**
- Scene-mode helper: **Use a clear, well-lit image that shows where you want to place the product.**
- Consent: **I agree that my uploaded image will be sent to the selected AI provider to generate this preview.**
- Primary action: **Generate Try-On**
- Progress: **Generating your try-on preview…**
- Success: **Your try-on preview is ready.**
- Limit error: **You have reached today’s try-on limit. Please try again tomorrow.**

### 7.6 AI Provider 与任务处理

| ID | Requirement |
| --- | --- |
| API-001 | 浏览器只与本站 WordPress REST API 通信；API Key 与 Provider 请求只发生在服务器端。 |
| API-002 | Provider 使用 adapter interface，统一处理鉴权、请求、超时、响应、错误映射和结果清理。 |
| API-003 | 外部请求使用 WordPress HTTP API；强制 HTTPS；设置合理连接/总超时；禁止加载或执行外部代码。 |
| API-004 | Provider Base URL 如允许配置，必须限制为 HTTPS，并防止访问 loopback、私网、link-local 和云元数据地址（SSRF）。 |
| API-005 | UI 使用异步 job 模型。Provider 可同步或异步，但前端统一通过任务状态查询。 |
| API-006 | 后台任务优先使用 WooCommerce 自带 Action Scheduler 的公开 API；任务必须幂等。 |
| API-007 | 任务状态：`queued`, `processing`, `succeeded`, `failed`, `expired`。 |
| API-008 | 请求至少包含顾客照片、可信产品图和最终 Prompt；不发送与生成无关的用户/订单数据。 |
| API-009 | Provider 错误映射为稳定的插件错误码；前端不显示原始响应、堆栈、Key 或内部路径。 |
| API-010 | 仅任务派发至 Provider 后占用每日额度；客户端校验失败或服务端预检失败不占额度。Provider 已接收后即使失败也计入额度，避免成本绕过。 |
| API-011 | “成功生成总数”只在获得可用结果后递增。任务重试必须具备原子/幂等保护，避免重复计数。 |
| API-012 | OpenAI 与 SeaAI 为互斥 Provider；不得把一个 Provider 的 Key、请求字段或结果转发给另一个 Provider。 |
| API-013 | 每个任务固定生成 1 张结果；结果只经临时任务交付，不创建 WordPress attachment，不进入 Media Library。 |

#### 7.6.1 OpenAI GPT Image 2 adapter

- 模型固定为 `gpt-image-2`；使用 OpenAI Image API，不使用 Responses API 的多轮会话能力。
- 试穿/场景融合使用 `POST https://api.openai.com/v1/images/edits`，以 `multipart/form-data` 发送。
- `image[]` 至少包含顾客/场景图和可信产品图；顾客/场景图排在第一张，产品图排在第二张，Prompt 明确每张参考图的角色。
- 必需/默认字段：`model=gpt-image-2`, `prompt`, `image[]`, `n=1`, `size=auto`, `quality=low`, `output_format=png`, `background=auto`。
- 商家可调整 OpenAI `quality`；MVP 不开放任意模型名、API Base URL、`n`、mask、透明背景或任意额外参数。
- 不发送 `input_fidelity`：GPT Image 2 对输入图自动使用 high fidelity，API 不允许修改该参数。
- 结果读取 `data[0].b64_json`，经严格 base64、大小和图片解码校验后写入临时结果存储。
- 复杂请求可能接近 2 分钟；任务超时与 UI 状态必须覆盖此延迟，不得让产品页主请求同步等待。
- 将 OpenAI `error.code` 映射为稳定插件错误码；对 `image_generation_user_error` 或 moderation 拒绝不得原样自动重试。

#### 7.6.2 SeaAI Universal X adapter

- SeaAI 是自定义 WordPress gateway，不按 OpenAI Images-compatible endpoint 处理。
- 鉴权使用 `Authorization: Bearer {SeaAI API Key}`；不得要求、显示或记录上游 Universal X Provider Key。
- 对每张本地输入图调用 `POST {base_url}/forward/image/upload`，multipart 字段为 `file`；成功响应必须包含 `download_url`。
- 随后调用 `POST {base_url}/forward/image/generate`，JSON 至少包含：

```json
{
  "model_name": "universal_x",
  "image_urls": ["customer-or-scene-url", "product-url"],
  "prompt": "...",
  "resolution": "auto",
  "size": "auto",
  "n": 1,
  "quality": "low",
  "background": "auto",
  "output_format": "png"
}
```

- `model_name` 固定为 `universal_x`；`quality` 默认 `low`，可选 `low`, `medium`, `high`；`background` 仅 `auto`, `opaque`；输出仅 `png`, `jpeg`。
- MVP 默认 `size/resolution=auto`, `n=1`, `background=auto`, `output_format=png`。
- 2xx 且 `images` 非空视为同步成功；数组元素可为 URL 字符串或带 `url` 的对象。
- 若响应返回 `task_id`，任务切换为轮询 `{base_url}/forward/image/query`；在 query contract 未补全前必须将该路径列为 SeaAI 集成测试前置条件。
- HTTP 400 为无效参数；401/403 为 Key 无效、禁用或限流；402 为积分不足；503 为 Universal X 未配置；502 为上游失败。
- 不自动重试 400、401、402、403；仅对网络、502、503 做有界指数退避。

建议 REST namespace：`sea-tryon/v1`。

建议端点：

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/jobs` | 校验图片和配额，创建任务。 |
| GET | `/jobs/{job_id}` | 返回当前会话有权查看的任务状态。 |
| GET | `/jobs/{job_id}/result` | 以短期授权方式返回或流式输出结果。 |
| DELETE | `/jobs/{job_id}` | 删除当前会话的临时输入、结果和任务数据。 |

所有端点必须定义 schema、参数校验、`permission_callback` 和明确 HTTP 状态。访客端点虽为公开可达，仍必须验证短期签名 token、同源请求、任务所有权和配额；不能依赖共享的 logged-out WordPress nonce 作为唯一防护。

### 7.7 限额规则

- **Allow Guest Try-On** 关闭时，匿名请求返回 401 `authentication_required`，且不占用任何额度。
- 拥有 `manage_options` 权限的 WordPress 管理员不执行创建预检和异步派发配额扣减，生成次数不受限制。
- 登录用户以 WordPress user ID + 站点时区日期计数。
- 允许访客时，访客以插件生成的高熵匿名 session ID + 站点时区日期计数；cookie 使用 Secure（HTTPS 时）、HttpOnly、SameSite=Lax。
- 登录用户与访客分别读取 **Daily Limit for Logged-in Users** 和 **Daily Limit for Guests**，不得共享同一个设置值。
- 不持久保存原始 IP；可使用带站点 secret 的短期 IP hash 作为额外滥用信号，但不能作为唯一身份。
- 限额重置时间采用 WordPress 站点时区，并在错误响应中返回下一次重置时间。
- 同一任务的状态轮询不计数。
- 并发创建必须采用原子锁或等价机制，防止并发绕过限额。

### 7.8 日志与统计

- 使用 `wc_get_logger()` / `WC_Logger`，source 为 `sea-tryon`。
- Debug Mode 关闭时仅记录必要的严重错误和任务结果摘要。
- Debug Mode 开启时可记录 Provider、模型、耗时、HTTP 状态、插件错误码、匿名 job ID。
- 永不记录：API Key、Authorization header、顾客图片/base64、可访问结果 URL、cookie/session token、完整 IP、Provider 原始敏感响应。
- 聚合统计不得包含个人照片、姓名、邮箱或订单信息。

## 8. 安全需求

1. 所有 PHP 文件阻止直接访问：`defined( 'ABSPATH' ) || exit;`。
2. 所有输入尽早 validate/sanitize，所有输出按上下文延迟 escape。
3. 管理操作同时使用 nonce 与 capability；nonce 不作为授权替代品。
4. REST 使用 `WP_REST_Request` 读取参数，不直接读取整个 `$_POST`/`$_GET`。
5. Job ID 和结果 token 至少使用 128 bit CSPRNG 随机值；不可顺序枚举。
6. 状态/结果查询必须验证任务属于当前 user/session。
7. 上传目录不可执行 PHP；文件名、路径、URL 均不可由顾客直接控制。
8. API Key 保存时使用密码字段、autoload off、日志脱敏；空白提交不得意外清除现有 Key。
9. Provider 响应必须限制体积、验证 Content-Type，并安全处理 JSON/base64/远程 URL。
10. 远程结果 URL 仅允许来自 Provider allowlist，且下载过程继续执行 SSRF 与大小限制。
11. 不使用 `Automattic\WooCommerce\Internal` 或标注 `@internal` 的 WooCommerce API。

## 9. 隐私与数据生命周期

顾客照片属于个人数据，可能包含生物识别特征。功能启用前商家必须能了解并披露第三方数据处理。

### 9.1 必须披露

- 收集的数据：顾客上传照片、产品图、产品 Prompt、生成结果、匿名任务标识和必要技术日志。
- 处理目的：生成虚拟试穿/试戴预览、防止滥用、排错。
- 接收方：商家所选择的 AI Provider。
- Provider 隐私政策与数据保留链接。
- 插件本地保留周期和顾客删除方式。
- AI 结果不保证尺码、合身度、颜色或现实效果。

### 9.2 同意与最小化

- 顾客首次提交前必须主动勾选同意项；不得预先勾选。
- 不发送姓名、邮箱、订单、地址或无关的 WordPress 用户资料。
- 不使用顾客照片训练插件开发者的模型，不向插件开发者发送照片或遥测。
- 商家负责确认所选 Provider 的服务条款和适用地区法律；插件提供可插入 WordPress Privacy Policy Guide 的建议文本。

### 9.3 保留与删除

- 输入图、结果图和 job 数据默认最多保留 24 小时，并尽可能在结果交付或失败后提前清理。
- 临时数据不进入 Media Library，不纳入公开图库。
- 顾客点击删除/取消任务时立即安排清理。
- 定时清理任务需幂等；插件停用/卸载时清理所有待处理任务和临时个人数据。
- 如未来引入账户历史，必须接入 WordPress Personal Data Exporter/Eraser；MVP 不建立历史，因此 exporter 应至少导出/删除仍存在的临时 job 数据（如技术上可定位）。

Provider 自身的保留策略不受插件本地删除控制，必须在同意说明和隐私文档中明确。

## 10. 可访问性需求

目标：WCAG 2.2 Level AA。

- 使用原生 `<button>`、`<input type="file">`、可见 `<label>` 和语义化状态信息。
- 打开模态框时焦点进入对话框；Tab/Shift+Tab 不可逃逸；Escape 关闭；关闭后焦点返回触发按钮。
- 所有操作支持键盘；焦点样式清晰且不移除 outline。
- 模态框使用 `role="dialog"`, `aria-modal="true"`, `aria-labelledby`。
- 上传错误靠近字段显示，并通过 live region 宣告。
- 颜色对比、触控目标、缩放、屏幕阅读器和 `prefers-reduced-motion` 满足 WooCommerce 指南。
- 加载动画不是唯一状态表达，并在 reduced motion 下关闭非必要动画。
- 自动化检查之外必须进行键盘、NVDA/VoiceOver、200%/400% 缩放人工测试。

## 11. 兼容性需求

草案基线基于当前本地环境与随附规范：

- WordPress：6.9+；当前验证环境 7.0.2。
- WooCommerce：10.9+；当前验证环境 10.9.4。
- PHP：7.4+。
- HTTPS：生产环境强烈要求；处理顾客照片和 API Key 时应视为必需。
- 浏览器：当前及前一个主要版本的 Chrome、Edge、Firefox、Safari；iOS Safari 与 Android Chrome。
- 产品类型：P0 支持 simple、variable；其他类型在满足图片与可购买条件时按 best-effort 显示。
- 主题：Storefront、WooCommerce 默认 block theme，以及至少两个常见第三方主题。
- WooCommerce 功能：产品字段集成 Classic Product Editor；不集成已在 WooCommerce 11.0 移除的 Product Editor Beta。前端需兼容 Site Editor、block theme 和 Single Product/Add to Cart blocks，且不得产生 fatal/error。插件不读取订单数据，因此 HPOS 不应影响功能。

只有完成对应测试后才能在插件 header/readme 中声明 `WC tested up to` 或组件兼容性。

## 12. 性能与可靠性

- 非目标页面不加载前端 JS/CSS。
- 管理资源只在本插件设置页和相关产品编辑页加载。
- 产品页初始渲染不得等待 Provider。
- 同一按钮操作只创建一个活跃任务；网络重放必须通过 idempotency key 去重。
- REST 状态轮询建议从 2 秒开始并退避，页面隐藏时暂停。
- Provider 超时、5xx、429 和网络错误应分类为可重试或不可重试；自动重试次数必须有限并带 backoff/jitter。
- 临时文件清理失败需记录脱敏错误，并由后续计划任务再次清理。
- 插件停用时取消/unschedule 自有 Action Scheduler actions；卸载时清理临时个人数据。

## 13. 建议数据模型

MVP 不创建自定义业务表，优先使用 WooCommerce/WordPress 公共 API。

### 13.1 Options（示意）

- `sea_tryon_enabled`
- `sea_tryon_provider`
- `sea_tryon_openai_api_key`（autoload off；允许常量/filter override）
- `sea_tryon_seaai_base_url`（autoload off；HTTPS URL）
- `sea_tryon_seaai_api_key`（autoload off；允许常量/filter override）
- `sea_tryon_openai_quality`
- `sea_tryon_seaai_quality`
- `sea_tryon_allow_guests`
- `sea_tryon_logged_in_daily_limit`
- `sea_tryon_guest_daily_limit`
- `sea_tryon_debug_mode`
- `sea_tryon_success_count`
- `sea_tryon_data_version`

### 13.2 Product meta（示意）

- `_sea_tryon_enabled`
- `_sea_tryon_prompt`
- `_sea_tryon_experience_type`

### 13.3 临时任务

- 使用高熵 job ID。
- 状态/所有权/时间/Provider task ID 使用短期 transient 或受控的临时存储。
- 图片文件存放在不可直接枚举、短期、受清理任务管理的位置；通过带任务所有权校验的结果端点交付。
- 若实际 Provider 或大规模并发证明 transient 不可靠，再通过数据迁移方案引入自定义表；不能在没有容量依据时预先建表。

## 14. 建议代码架构

- 单一主插件文件 `sea-tryon.php`，只负责 header、常量、依赖检查、生命周期 hook 和 bootstrap。
- `includes/`：插件容器/loader、Provider interface、job service、quota service、privacy service、logger。
- `includes/Admin/`：WooCommerce 设置页、产品字段和统计。
- `includes/Rest/`：基于 `WP_REST_Controller` 的 job endpoints 与 schema。
- `includes/Frontend/`：按钮渲染、资源加载和本地化数据。
- `templates/`：可覆盖的前端模板，保持业务逻辑与展示分离。
- `assets/src/` 与构建后的 `assets/js`, `assets/css`。
- 翻译目录不随插件源码发布；所有源字符串仍使用 `seatryon-ai-virtual-try-on-for-woocommerce` text domain。
- `uninstall.php`：仅在 `WP_UNINSTALL_PLUGIN` 下运行，遵循保留策略。

对外扩展点至少覆盖：按钮可见性/位置、最终 Prompt、产品图选择、限额、文件限制、Provider 注册、临时数据 TTL。扩展点不得允许低权限用户绕过安全校验。

## 15. 错误模型与默认英文提示

| Code | HTTP | Default message |
| --- | --- | --- |
| `tryon_not_enabled` | 403 | Virtual Try-On is not available for this product. |
| `invalid_upload` | 400 | Please upload a valid JPEG, PNG, or WebP image. |
| `file_too_large` | 413 | This image is too large. Please choose a smaller file. |
| `consent_required` | 400 | Please agree to the photo processing notice before continuing. |
| `authentication_required` | 401 | Please log in to use Virtual Try-On. |
| `quota_exceeded` | 429 | You have reached today’s try-on limit. Please try again tomorrow. |
| `job_not_found` | 404 | This try-on request could not be found or has expired. |
| `provider_rate_limited` | 503 | The image service is busy. Please try again shortly. |
| `provider_rejected` | 422 | This image could not be processed. Please choose another photo. |
| `provider_insufficient_balance` | 503 | Virtual Try-On is temporarily unavailable. Please contact the store. |
| `generation_failed` | 502 | We could not generate the preview. Please try again. |
| `configuration_error` | 503 | Virtual Try-On is temporarily unavailable. Please contact the store. |

原始 Provider 错误只进入脱敏日志，不直接显示给顾客。

## 16. 验收标准

### 16.1 商家功能

- [ ] WooCommerce 未激活时，插件不 fatal，并给管理员明确提示。
- [ ] 管理员可保存全局开关、互斥 Provider、对应 Key、访客开关、两类限额和 Debug Mode。
- [ ] 非 `manage_woocommerce` 用户无法查看/修改设置或重置统计。
- [ ] 已保存 Key 不会出现在页面源码、REST 响应、日志或普通错误中。
- [ ] 选择 OpenAI 时只调用 OpenAI；选择 SeaAI 时只调用 SeaAI，且另一 Provider 的 Key 不进入请求。
- [ ] 产品启用但 Prompt/产品图缺失时不能形成无效配置。
- [ ] Experience Type 和产品字段在 Classic Product Editor 的简单产品与可变产品流程中保存正确。

### 16.2 顾客功能

- [ ] 只有符合显示条件的产品显示 **Virtual Try-On**。
- [ ] 上传有效照片可完成一条端到端生成任务并显示结果。
- [ ] 伪造 MIME、超大文件、无效 token、越权 job ID 均被拒绝。
- [ ] 顾客不能读取其他 user/session 的任务状态或结果。
- [ ] 访客开关关闭时匿名用户获得登录提示且无法创建任务；开启时可按访客限额生成。
- [ ] 登录用户和访客分别执行各自的每日限额设置。
- [ ] 达到每日限额后返回 429 和可理解提示；次日按站点时区恢复。
- [ ] Variation 改变时使用当前 Variation 图片并使旧任务输入失效。
- [ ] 人物试穿与家具/商品场景融合各至少有一条端到端成功用例。
- [ ] 关闭/重开模态框可在同页恢复当前任务状态。

### 16.3 隐私与清理

- [ ] 未勾选同意时不能提交。
- [ ] 图片不进入 Media Library，不出现在公共附件列表。
- [ ] 超过 TTL 的输入、输出和任务记录自动清理。
- [ ] 停用/卸载后不残留待执行的插件 Action Scheduler actions。
- [ ] Debug 日志中不存在 Key、Authorization、图片数据和完整 IP。

### 16.4 可访问性与兼容性

- [ ] 模态框完整支持键盘、焦点捕获/恢复和 Escape。
- [ ] 状态变化由屏幕阅读器可感知。
- [ ] 320 px、200%/400% 缩放下功能可用。
- [ ] Storefront、block theme、simple product、variable product 通过 E2E。
- [ ] WP_DEBUG 开启时无 PHP warning/notice/deprecation，且不调用已弃用的 Product Editor Beta API。
- [ ] PHPCS WordPress/WooCommerce 规则、PHPUnit、JS lint、E2E 与 QIT 兼容测试通过。

## 17. 测试计划

1. **PHP unit tests**：Prompt 组合、quota、状态机、错误映射、脱敏、TTL。
2. **REST integration tests**：schema、权限、token、所有权、重复提交、429、任务过期。
3. **Provider contract tests**：分别覆盖 OpenAI multipart edit/base64 响应与 SeaAI upload/generate/query；使用 mock HTTP 响应覆盖成功、同步/异步、402、429、5xx、超时、畸形 JSON、超大响应。
4. **Upload security tests**：双扩展名、伪 MIME、损坏图、超大尺寸、SVG、路径穿越、EXIF 方向。
5. **E2E**：商家设置、Provider 二选一、产品配置、人物/场景模式、simple/variable 产品、访客开关、访客/登录用户独立限额与完整流程。
6. **Accessibility**：axe/WAVE + 键盘 + NVDA/VoiceOver + reduced motion + zoom。
7. **Compatibility**：目标最低/最高 WP、WooCommerce、PHP；Storefront 与 block theme；常用缓存/安全插件共存。
8. **Operational**：Action Scheduler 延迟、WP-Cron 关闭、多次任务执行、清理失败恢复、插件停用/卸载。

## 18. 产品决策

### 18.1 已确认

1. OpenAI 与第三方 API 为二选一 Provider，同一生成任务不串联调用。
2. OpenAI 使用 `gpt-image-2` 的 Image Edit 规范。
3. 第三方使用 SeaAI WordPress gateway 的 `universal_x`；默认 `quality=low`。
4. 支持服装、饰品、眼镜、假发、家具和一般商品场景融合。
5. 提供默认关闭的 **Allow Guest Try-On**；启用后访客每日次数才生效，普通登录用户使用独立每日次数，商店管理员不受限制。
6. 生成结果不进入 WordPress Media Library。
7. 不为已在 WooCommerce 11.0 移除的 Product Editor Beta 开发集成；使用 Classic Product Editor。

### 18.2 仍需确认

1. **限额默认值**：是否接受登录用户默认 3 次/日、访客默认 3 次/日、访客功能默认开启？
2. **生成结果**：每次固定生成 1 张并允许下载是否满足首发要求？
3. **保留周期**：本草案默认插件本地最多保留 24 小时。是否要求结果交付后立即删除，或提供商家可配置 TTL？
4. **兼容范围**：是否接受 WordPress 6.9+、WooCommerce 10.9+、PHP 7.4+，并额外验证 WooCommerce 11.x？
5. **SeaAI query contract**：请补充 `/forward/image/query` 的请求/响应示例，或确认生产环境 `universal_x` 始终同步返回 `images`。
6. **测试凭证**：开发阶段是否提供 OpenAI 与 SeaAI 的测试 Key/Base URL？
7. **产品命名与发布渠道**：最终插件名、作者/公司、许可证、更新渠道，以及是否计划提交 WordPress.org 或 WooCommerce Marketplace。

## 19. 需求冻结条件

满足以下条件后，文档可升级为 v1.0 并进入实现：

1. 第 18.2 节至少确认限额默认值、生成结果、保留周期、兼容基线与 SeaAI query contract。
2. 获得 OpenAI 与 SeaAI 可用于开发环境的测试凭证。
3. 确认隐私政策链接、用户同意文案和 Provider 数据保留规则。
4. 确认产品名、slug/text domain 与发布渠道。
5. 对 P0/P1 边界和验收标准完成评审。

## 20. 参考资料

- `sea-tryon-doc/tryon入口（在产品页前端）.png`
- `sea-tryon-doc/设置(Key等).png`
- `sea-tryon-doc/设置提示词（product页面编辑）.png`
- `sea-tryon-doc/官方开发规范/best-practices-extensions/extension-development-best-practices.md`
- `sea-tryon-doc/官方开发规范/best-practices-extensions/compatibility.md`
- `sea-tryon-doc/官方开发规范/best-practices-extensions/gdpr-compliance.md`
- `sea-tryon-doc/官方开发规范/best-practices-extensions/privacy-standards.md`
- `sea-tryon-doc/官方开发规范/best-practices-extensions/accessibility.md`
- `sea-tryon-doc/官方开发规范/settings-and-config/implementing-settings.md`
- `sea-tryon-doc/官方开发规范/settings-and-config/extend-wc-settings-page.md`
- `sea-tryon-doc/官方开发规范/core-concepts/check-if-woo-is-active.md`
- `sea-tryon-doc/官方开发规范/core-concepts/maintainability.md`
- `sea-tryon-doc/官方开发规范/core-concepts/handling-deactivation-and-uninstallation.md`
- external SeaAI Universal X contract reference (local path intentionally omitted)
- external SeaAI Universal X contract reference (local path intentionally omitted)
- [OpenAI GPT Image 2 model](https://developers.openai.com/api/docs/models/gpt-image-2)
- [OpenAI Image generation guide](https://developers.openai.com/api/docs/guides/image-generation)
- [WooCommerce Product Editor Beta retirement](https://developer.woocommerce.com/2026/06/02/product-editor-beta-retiring/)