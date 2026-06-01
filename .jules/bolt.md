# Bolt's Journal - Critical Learnings

## 2025-05-14 - [O(N) Lookups and Redundant Queries]
**Learning:** In PHP-based management systems, fetching a list of "active" or "online" entities into an indexed array and using `in_array()` during a loop over all entities creates an $O(N \times M)$ performance bottleneck. Additionally, executing multiple `COUNT` queries for different statuses on the same table increases database round-trips unnecessarily.

**Action:**
1. Use associative arrays (hash maps) for status lookups to achieve $O(1)$ lookup time with `isset()`.
2. Combine multiple status counts into a single query using conditional aggregation (`SUM(CASE WHEN ... THEN 1 ELSE 0 END)`).
3. Reuse already fetched data (like the online users list) to derive counts instead of re-querying the database.
