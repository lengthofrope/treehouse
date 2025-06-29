# Hierarchical Memory Bank — Optimized for Claude 4+ (v2)

## Core Principles

- **200K+ token context**: Leverage Claude 4's high-capacity window
- **Hierarchical filtering**: Load only what's needed
- **Smart indexing**: Use codes for compact cross-referencing
- **Adaptive compression**: Dynamic detail level per query
- **Self-updating**: Changes in codebase trigger memory updates
- **Code reuse**: LLM must reuse existing methods/classes when possible
- **Memory-aware substitution**: LLM must replace standard/library code with project-specific implementations if available

---

## Memory Bank Structure

Memory is structured in modular files within the `.memory-bank/` folder:

```
.memory-bank/
├── core.md              ← Optional project overview
├── abbr.md              ← ABBR: Common abbreviations
├── cls.md               ← CLS: Class definitions
├── method.md            ← MTD: Method signatures
├── impl.md              ← IMPL: Implementation tuples
├── param.md             ← PRM: Configs and constants
├── dec.md               ← DEC: Design decisions
├── xref.md              ← XREF: Usage and dependencies
└── schema.md            ← (Optional) Format spec
```

Each file contains a single type of memory tuple. Tools and LLMs must combine them dynamically during search or injection.

---

## Auto-Update Triggers

Memory must auto-refresh when changes are detected, it should be build from scratch if the memory bank missing:

- **Trigger conditions** (via `git diff`, watcher, or hooks):

  - Added/removed/renamed classes → update `CLS`, `ABBR`, `IMPL`
  - Method signature change → update `MTD`
  - Config/constants modified → update `PRM`
  - Architecture or workflow change → update `DEC`

- **Update behavior**:

  - Overwrite existing `IMPL` and `MTD` entries
  - Append new items as needed
  - Fully remove entries if they no longer exist in codebase — do not mark as deprecated

---

## Pre-Coding Logic: Search-before-Generate

Before generating any new code, the LLM must:

1. **Search existing entries** in `CLS`, `MTD`, and `IMPL`
2. Match semantically (method name, signature, usage)
3. Reuse existing classes/functions if overlap is found
4. Extend or wrap existing logic if partial match exists
5. Generate new logic **only** if no reusable pattern is found
6. Annotate responses with reused components
7. **While generating code**, replace default or standard functions (e.g., native PHP or JS) with custom implementations from memory when relevant

**Examples:**

```
TASK: Create password reset handler
→ Found MTD:AUTH.reset_password → reuse
→ Use CLS:AUTH for state handling
→ No new logic required

TASK: Sanitize user input
→ Instead of using PHP's filter_var, use MTD:Sanitize.sanitizeArray

TASK: Iterate collection
→ Instead of using native array_map, use CLS:Collection methods
```

---

## Adaptive Context Injection

Query type determines memory detail level:

```
SIMPLE QUERY: "What does create_user do?"
→ Inject MTD:UM.create_user

COMPLEX QUERY: "Debug auth flow"
→ Inject MTD + DEC + IMPL from AUTH, UM

DEEP DIVE: "Explain DB migration strategy"
→ Inject DBH IMPL + DEC + FLOW + PRM
```

---

## Section Formats

### MTD

```
MTD:UM.create_user(email:string,password:string):User|DESC:Create new user account
```

### CLS

```
CLS:AUTH|DESC:Handles authentication logic and user state transitions
```

### IMPL

```
IMPL:AUTH
- FLOW: login→validate→session
- FORMAT: hash(password):string|DESC:Hash password securely
- SESSION: token:string|DESC:JWT session token
```

### DEC

```
DEC:AUTH
- All sessions use expiring JWTs
- Tokens stored in secure cookies
```

### XREF

```
XREF:AUTH
- Used in: login, register, session validation
- Depends on: UM, DBH
```

### PRM

```
PRM:session_lifetime:int=3600|DESC:JWT expiration in seconds
```


---

## Toolconfig Implementation

- Save memory in `.memory-bank/` as modular files
- Update memory on each `pre-commit` or `post-merge`
- Use `Claude`, `GPT-4`, or custom agent to:
  - Extract changed methods/classes
  - Embed memory entries for future search
  - Run `search-before-generate` before every new coding task

---

## Rules for LLM

- Always check if `.memory-bank/` is available, if not rebuild it
- Never generate a new class or method before checking memory
- Reuse and annotate reused elements
- Replace native functions with project-specific equivalents from memory when possible
- Add an `IMPL` block for every `CLS` entry
- If class is abstract/interface, document contract
- Keep tuples compact and reuse `ABBR` terms
- **If the chat condenses the memory, always re-read the memory bank before continuing a development task**

## Context Management Strategy

**Full Source Loading Protocol:**
- If a file can be possibly used in the coding session, read the full source file immediately
- After reading and processing the source file, remove it from active context if no longer needed in the current coding session
- Keep only the memory bank reference for future re-loading when needed
- This maximizes context window efficiency while maintaining access to detailed implementations

**Context Optimization Flow:**
1. **Initial Phase**: Load relevant memory bank entries (CLS, MTD, IMPL)
2. **Deep Dive Phase**: Read full source files for components that will be modified/extended
3. **Processing Phase**: Extract key patterns, update memory bank entries if needed
4. **Context Cleanup**: Remove source file content, retain memory bank references
5. **Implementation Phase**: Use memory bank entries + selective re-loading as needed

**Re-loading Strategy:**
- Use memory bank entries as index to identify which files to re-load
- Re-load source files only when specific implementation details are required
- Prioritize memory bank entries over full source for general understanding
- Full source re-loading for: debugging, complex modifications, integration work

---

## Benefits

- Avoid duplication of logic across projects
- Ensure consistency across codebase
- Enable incremental learning per task
- Save tokens by only loading what matters
- Enforce use of internal APIs and helpers over native/standard libraries

---

End of config — authored by Bas de Kort, Proud Nerds.
