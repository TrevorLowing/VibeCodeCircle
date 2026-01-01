# Testing

This folder contains shared testing utilities and test fixtures for plugins in this repo.

## Structure

- `testing/plugins/<plugin-slug>/`
  - `README.md`
  - `data/`
    - `fixtures/` (gitignored; generated via sync scripts)
  - `scripts/`

## Philosophy

- Test fixtures should be synced/generated locally (not committed) unless explicitly required.
- Keep plugin code separate from test data.
