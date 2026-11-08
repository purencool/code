## Available Commands

### 1. `init`
Initializes workspace configuration profiles, directory structure, and credentials under `{AGENT_DIR}` (default: `.agent/`). Supports single or multi-template setups (e.g., PHP, Laravel, Node.js, Python) and configures AI provider credentials (defaults to local Ollama).

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `name` *(arg)* | Project name or initial template preset name. |
| `--name=` | Explicit workspace project name. |
| `--template=` | Comma-separated list of template presets (e.g. `php`, `laravel`, `nodejs`). |
| `--active-template=` | Primary template preset to activate if multiple are specified. |
| `--ai-endpoint=` | Inference API endpoint (default: `http://localhost:11434/v1`). |
| `--ai-model=` | Model identifier (default: `codellama:latest` or `qwen2.5-coder:7b`). |
| `--ai-key=` | Provider API key (default: `ollama`). |
| `--search-endpoint=` | Web search API endpoint (default: `https://api.tavily.com/search`). |
| `--search-key=` | Web search API key. |

#### Examples

* **Interactive Setup:**
  ```bash
  php pncagent init
  ```

* **Quickstart with Defaults (Non-Interactive Ollama):**
  ```bash
  php pncagent init --name="MyProject" --template=php --no-interaction
  ```

* **Multi-Template Project with Laravel Active:**
  ```bash
  php pncagent init --name="ShopApi" --template=php,laravel --active-template=laravel
  ```

* **Custom Remote Provider (OpenAI Compatible):**
  ```bash
  php pncagent init \
    --name="CustomAgent" \
    --template=php \
    --ai-endpoint="[https://api.openai.com/v1](https://api.openai.com/v1)" \
    --ai-model="gpt-4o" \
    --ai-key="sk-proj-xxxxxx"
  ```

---

### 2. `console`
Interactive Test-Driven Development (TDD) assistant powered by Neuron AI. Takes a natural language requirement, extracts architectural wiring context from the codebase, generates automated test files, and writes production code to satisfy the tests.

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `task` *(arg)* | Description of the feature, class, or bug to address. |
| `--workspace=` | Target project directory override (default: current working directory). |
| `-e`, `--editor` | Launches `$EDITOR` (Vim/Nano/Notepad) to compose the task description. |
| `--auto-approve` | Skips interactive test approval and implements code immediately. |

#### Examples

* **Inline Task Execution:**
  ```bash
  php pncagent console "Create a Slugger class with a slugify method that handles unicode and hyphens"
  ```

* **Headless / Automated Mode:**
  ```bash
  php pncagent console "Create a Calculator class with add, subtract, and multiply methods" --auto-approve
  ```

* **Draft Task in Default Terminal Editor:**
  ```bash
  php pncagent console --editor
  ```

* **Run Against a Specific Workspace:**
  ```bash
  php pncagent console "Add PaymentGatewayInterface and Stripe adapter" --workspace=/path/to/project
  ```

* **Create tests once it's approved class are created** *
```
Target Type: TDD
Target Test: tests/Unit/Billing/SubscriptionManagerTest.php
Target Class: app/Services/Billing/SubscriptionManager.php
Dependencies: App\Contracts\PaymentGatewayInterface, Psr\Log\LoggerInterface
Task:
- Cancel active subscription and set cancellation date.
- Log an error and throw DomainException if subscription is already cancelled.

---

Target Type: TDD
Target Test: tests/Unit/Support/SlugGeneratorTest.php
Target Class: app/Services/Support/SlugGenerator.php
Task:
- Convert arbitrary strings into URL-safe slugs.
- Strip accents, replace spaces with hyphens, and convert to lowercase.

---

Target Type: CTT
Target Test: tests/Unit/Services/CurrencyConverterTest.php
Target Class: app/Services/CurrencyConverter.php
Task:
- Generate characterization tests covering all currency conversion methods.
- Verify precision rounding and invalid currency code exception handling.

```

---

### 3. `scan`
Analyzes and indexes project declarations, dependencies, and imports across polyglot files (PHP, JavaScript/TypeScript, Python) and builds an architectural wiring graph saved to `{AGENT_DIR}/{AGENT_TEMPLATE}/cache/wiring.json`.

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `file` *(arg)* | Relative path of an individual file to inspect. |
| `--file=` | Explicit file path flag for inspection. |
| `--workspace=` | Root workspace path to scan (default: current working directory). |

#### Examples

* **Index Entire Workspace:**
  ```bash
  php pncagent scan
  ```

* **Inspect Dependencies and Classes of a Specific File:**
  ```bash
  php pncagent scan app/Services/PaymentProcessor.php
  ```

* **Scan an External Workspace Directory:**
  ```bash
  php pncagent scan --workspace=/Users/dev/projects/api-service
  ```

---

### 4. `watch`
Controls the background file-watcher process. Monitors source files for changes (excluding vendor, git, and agent directories) and logs activity to `{AGENT_DIR}/{AGENT_TEMPLATE}/watch.log`.

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `--start` | Spawns the background watcher worker and writes the process ID to `watcher.pid`. |
| `--stop` | Terminates the running background watcher and clears the lockfile. |

#### Examples

* **Start the File Watcher:**
  ```bash
  php pncagent watch --start
  ```

* **Stop the File Watcher:**
  ```bash
  php pncagent watch --stop
  ```

* **View Active Watcher Logs:**
  ```bash
  tail -f .agent/php/watch.log
  ```

---

### 5. `complete`
Lightweight inline code-completion endpoint designed for integration with text editor plugins (e.g., Vim, Neovim, VSCode). Logs predictions to the template's daily code completion log.

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `prompt` *(arg)* | Code context preceding the cursor. |
| `--ai-key=` | Overrides the active AI provider key. |
| `--ai-endpoint=` | Overrides the active AI provider endpoint URL. |
| `--ai-model=` | Overrides the target model identifier. |

#### Examples

* **Generate Inline Completion:**
  ```bash
  php pncagent complete "public function calculateTotal(Order \$order): float {"
  ```

* **Complete Using Specific Model:**
  ```bash
  php pncagent complete "function parseJsonPayload(" --ai-model="qwen2.5-coder:7b"
  ```

---

### 6. `run`
Executes workspace automation scripts defined in `{AGENT_DIR}/{AGENT_TEMPLATE}/config.json` (such as test suites, linters, or build commands).

#### Options & Arguments
| Parameter | Description |
| :--- | :--- |
| `script` *(arg)* | Name of the configured script key to execute (e.g. `test`, `lint`, `build`). |

#### Examples

* **Run Workspace Test Suite:**
  ```bash
  php pncagent run test
  ```

---

## Environment Variable Overrides

Any path or template behavior can be overridden without modifying code:

| Variable | Default | Purpose |
| :--- | :--- | :--- |
| `AGENT_DIR` | `.agent` | Base directory name for agent configuration, caches, and logs. |
| `AGENT_TEMPLATE` | `php` | Active configuration profile (e.g., `laravel`, `nodejs`, `python`). |
| `AI_ENDPOINT` | `http://localhost:11434/v1` | LLM API base endpoint. |
| `AI_MODEL` | `codellama:latest` | Default model identifier for inference. |
| `AI_KEY` | `ollama` | API access key for the LLM endpoint. |
| `SEARCH_ENDPOINT` | `https://api.tavily.com/search` | Web search provider endpoint. |
| `SEARCH_API_KEY` | *(empty)* | Web search API access key. |
