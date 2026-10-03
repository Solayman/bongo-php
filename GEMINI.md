# Project Instructions & Memory

## Git & Branching Strategy
- **Main Branch**: `master` (production-ready code).
- **Working / Push Branch**: `solayman` (all development code must be pushed to the `solayman` branch).
- **Merge Requests / Pull Requests**:
  1. Commit code to `solayman`.
  2. Push to `origin/solayman`.
  3. Create PR/MR targeting `master` (`gh pr create --base master --head solayman`).
  4. Merge PR/MR into `master` (`gh pr merge <number> --merge`).
  5. Sync local `master` and `solayman` branches.
