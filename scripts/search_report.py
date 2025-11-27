import argparse
import html
import os
import sys
import time
from html.parser import HTMLParser
from typing import Dict, Iterable, List, Sequence, Tuple
import urllib.parse
import urllib.request

USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/125.0.0.0 Safari/537.36"
)


def fetch_html(query: str, region: str, delay: float = 1.0) -> str:
    params = {
        "q": query,
        "hl": "en",
        "gl": region,
    }
    url = "https://www.google.com/search?" + urllib.parse.urlencode(params)
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=15) as resp:  # type: ignore[arg-type]
        content = resp.read().decode("utf-8", errors="ignore")
    if delay:
        time.sleep(delay)
    return content


def page_blocked(page_html: str) -> bool:
    block_indicators = [
        "httpservice/retry/enablejs",
        "detected unusual traffic",
        "Our systems have detected",
        "enable JavaScript",
    ]
    return any(token in page_html for token in block_indicators)


class GoogleResultParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.results: List[Tuple[str, str]] = []
        self._in_result = False
        self._in_h3 = False
        self._url: str | None = None
        self._title_parts: List[str] = []

    def handle_starttag(self, tag: str, attrs: List[Tuple[str, str | None]]):
        if tag == "a":
            attrs_dict = {k: v for k, v in attrs if v is not None}
            href = attrs_dict.get("href", "")
            if href.startswith("/url?q="):
                self._in_result = True
                parsed_url = href.split("/url?q=")[1]
                self._url = parsed_url.split("&")[0]
        if self._in_result and tag == "h3":
            self._in_h3 = True

    def handle_endtag(self, tag: str):
        if tag == "h3":
            self._in_h3 = False
        if tag == "a" and self._in_result:
            title = "".join(self._title_parts).strip()
            if self._url and title:
                self.results.append((html.unescape(title), html.unescape(self._url)))
            self._reset()

    def handle_data(self, data: str):
        if self._in_result and self._in_h3:
            self._title_parts.append(data)

    def _reset(self):
        self._in_result = False
        self._in_h3 = False
        self._url = None
        self._title_parts = []


def parse_results(page_html: str) -> List[Tuple[str, str]]:
    parser = GoogleResultParser()
    parser.feed(page_html)
    unique: Dict[str, Tuple[str, str]] = {}
    for title, url in parser.results:
        if url not in unique:
            unique[url] = (title, url)
    return list(unique.values())


def build_report(
    keywords: Sequence[str],
    regions: Dict[str, str],
    targets: Sequence[str],
    limit: int,
    delay: float,
) -> Dict[str, Dict[str, Dict[str, List[Tuple[str, str]]]]]:
    report: Dict[str, Dict[str, Dict[str, List[Tuple[str, str]]]]] = {}
    for region_label, gl_code in regions.items():
        report[region_label] = {}
        for keyword in keywords:
            html_text = fetch_html(keyword, gl_code, delay=delay)
            blocked = page_blocked(html_text)
            results = [] if blocked else parse_results(html_text)[:limit]
            hits = [(title, url) for title, url in results if any(t in url for t in targets)]
            entry: Dict[str, List[Tuple[str, str]] | str] = {
                "results": results,
                "target_hits": hits,
            }
            if blocked:
                entry["error"] = "Results blocked by Google (JS or anti-bot prompt)."
            report[region_label][keyword] = entry  # type: ignore[assignment]
    return report


def render_markdown(report: Dict[str, Dict[str, Dict[str, List[Tuple[str, str]]]]], targets: Sequence[str]) -> str:
    lines: List[str] = []
    lines.append("# Google Search Coverage Report")
    lines.append("")
    lines.append("This report lists the first results returned by Google for the provided keywords across the specified regions.")
    lines.append("Results are limited to the top entries returned by the parser and may vary on repeated runs.")
    lines.append("")
    lines.append("## Target URLs")
    for url in targets:
        lines.append(f"- {url}")
    lines.append("")

    for region, keywords in report.items():
        lines.append(f"## Region: {region}")
        for keyword, data in keywords.items():
            lines.append(f"### Keyword: `{keyword}`")
            results: List[Tuple[str, str]] = data.get("results", [])  # type: ignore[assignment]
            hits: List[Tuple[str, str]] = data.get("target_hits", [])  # type: ignore[assignment]
            error_message = data.get("error")  # type: ignore[assignment]

            if error_message:
                lines.append(f"> {error_message}")
                lines.append("")

            if hits:
                lines.append("**Target URL matches in results:**")
                for title, url in hits:
                    lines.append(f"- [{title}]({url})")
            else:
                lines.append("**No target URLs found in parsed results.**")
            lines.append("")

            if results:
                lines.append("Top parsed results:")
                for idx, (title, url) in enumerate(results, start=1):
                    lines.append(f"{idx}. [{title}]({url})")
            else:
                lines.append("No results parsed.")
            lines.append("")
        lines.append("")
    return "\n".join(lines)


def main(argv: Iterable[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Generate Google search coverage report.")
    parser.add_argument("--limit", type=int, default=10, help="Number of results to keep per keyword.")
    parser.add_argument("--delay", type=float, default=1.0, help="Delay between requests in seconds.")
    parser.add_argument(
        "--output",
        type=str,
        default=os.path.join("search_reports", "search_results.md"),
        help="Path to write the markdown report.",
    )
    args = parser.parse_args(list(argv) if argv is not None else None)

    keywords = [
        "Christian Carter Dublin",
        "Christian Carter Ireland",
        "Christian Carter Landlord",
        "Christian Carter Landlord Ireland",
        "Christian Carter Landlord Dublin",
        "Christian Carter",
    ]

    regions = {
        "USA": "us",
        "CAD": "ca",
        "Ireland": "ie",
        "UK": "gb",
    }

    targets = [
        "https://www.independent.ie/irish-news/playing-the-victim-the-whatsapps-emails-and-lies-that-put-a-landlord-back-on-the-front-page-after-he-tried-to-gag-a-journalist/a301771338.html",
        "https://www.thejournal.ie/judge-throws-out-landlords-attempt-to-silence-irish-independent-journalist-using-anti-stalking-law-6680234-Apr2025/",
        "https://www.newstalk.com/news/landlords-gagging-order-against-irish-journalist-thrown-out-2155736",
        "https://www.irishtimes.com/tags/christian-carter/",
        "https://evoke.ie/2017/02/06/entertainment/celebrity/business-woman-condemns-brother-for-housing-70-tenants-in-one-property",
        "https://www.independent.ie/irish-news/landlord-christian-carter-tops-tax-defaulters-list-with-1m-settlement/a1654030541.html",
        "https://www.msn.com/en-ie/news/world/court-rejects-landlord-turned-screenwriters-attempt-to-use-anti-stalking-laws-to-silence-journalist/ar-AA1D3yl5",
        "https://www.newstalk.com/podcasts/highlights-from-the-pat-kenny-show/landlord-case-against-journalist-thrown-out",
        "https://www.sundayworld.com/crime/courts/rogue-landlord-christian-carter-hit-with-5k-legal-bill-after-backing-off-from-silencing-media/a103910979.html",
        "https://www.rte.ie/news/courts/2025/0506/1511406-christian-carter-court/",
        "https://www.irishmirror.ie/news/irish-news/court-rejects-landlord-turned-screenwriters-35069370",
        "https://www.independent.ie/podcasts/the-indo-daily/the-indo-daily-two-showers-for-70-people-how-rogue-landlord-christian-carter-exploited-the-housing-crisis/a1497269270.html",
        "https://www.thejournal.ie/landlord-court-case-3225473-Feb2017/",
        "https://omny.fm/shows/the-indo-daily/two-showers-for-70-people-how-rogue-landlord-chr-1",
        "https://www.irishtimes.com/news/social-affairs/landlord-in-rte-expose-has-record-of-overcrowded-housing-1.3279339",
        "https://www.rte.ie/news/business/2024/0611/1454217-landlord-based-in-mexico-tops-tax-defaulters-list/Target Keywords:",
    ]

    try:
        report = build_report(keywords, regions, targets, limit=args.limit, delay=args.delay)
    except Exception as exc:  # pragma: no cover - diagnostics for network issues
        print(f"Failed to build report: {exc}", file=sys.stderr)
        return 1

    markdown = render_markdown(report, targets)
    os.makedirs(os.path.dirname(args.output), exist_ok=True)
    with open(args.output, "w", encoding="utf-8") as f:
        f.write(markdown)

    print(f"Report written to {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
