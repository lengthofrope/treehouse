# Memory Bank Schema Documentation

## File Structure
The memory bank is organized into modular files, each containing a specific type of information:

### Core Files
- **core.md**: Project overview and architecture
- **abbr.md**: Common abbreviations and acronyms
- **cls.md**: Class definitions and descriptions
- **method.md**: Method signatures and descriptions
- **impl.md**: Implementation details and patterns
- **param.md**: Configuration parameters and constants
- **dec.md**: Design decisions and architectural choices
- **xref.md**: Cross-references and dependencies
- **schema.md**: This documentation file

## Format Specifications

### ABBR Format
```
ABBR_NAME: Full description
```

### CLS Format
```
CLS:ClassName|DESC:Brief description of class purpose and functionality
```

### MTD Format
```
MTD:ClassName.methodName(param1:type, param2:type):returnType|DESC:Method description
```

### IMPL Format
```
IMPL:ComponentName
- FEATURE: Implementation detail
- PATTERN: Design pattern used
- FLOW: Process flow description
```

### PRM Format
```
PRM:config.key:type=defaultValue|DESC:Parameter description
```

### DEC Format
```
DEC:ComponentName
- Decision description
- Rationale and considerations
```

### XREF Format
```
XREF:ComponentName
- Used in: List of components that use this
- Depends on: List of dependencies
- Provides: What this component provides
- Additional context
```

## Usage Guidelines

### Search Strategy
1. Start with **CLS** for class overview
2. Use **MTD** for method signatures
3. Check **IMPL** for implementation details
4. Reference **XREF** for dependencies
5. Consult **DEC** for design rationale

### Update Protocol
1. **Automatic triggers**: File changes, git hooks
2. **Manual updates**: Architecture changes, new features
3. **Validation**: Ensure consistency across files
4. **Cleanup**: Remove obsolete entries

### Integration Points
- **IDE integration**: Autocomplete and documentation
- **Code generation**: Template and stub creation
- **Testing**: Test case generation from signatures
- **Documentation**: API documentation generation

## Memory Bank Benefits

### For Developers
- **Quick reference**: Instant access to class and method information
- **Code reuse**: Identify existing implementations before creating new ones
- **Architecture understanding**: Clear view of system design
- **Dependency tracking**: Understand component relationships

### For AI/LLM
- **Context efficiency**: Compact representation of large codebase
- **Pattern recognition**: Identify reusable patterns and implementations
- **Consistency**: Ensure consistent coding patterns
- **Smart suggestions**: Recommend existing solutions over new implementations

### For Project Management
- **Impact analysis**: Understand change implications
- **Refactoring guidance**: Identify refactoring opportunities
- **Documentation**: Maintain up-to-date system documentation
- **Knowledge transfer**: Facilitate team onboarding

## Maintenance Rules

### Consistency Requirements
- All class names must be in CLS entries
- All public methods must have MTD entries
- All configuration parameters must be in PRM
- Cross-references must be bidirectional

### Update Frequency
- **Real-time**: Critical changes (API modifications)
- **Daily**: Regular development changes
- **Weekly**: Documentation and cleanup
- **Release**: Complete validation and cleanup

### Quality Assurance
- **Automated validation**: Check for missing entries
- **Manual review**: Verify accuracy and completeness
- **Testing integration**: Validate against actual codebase
- **Peer review**: Team validation of major changes