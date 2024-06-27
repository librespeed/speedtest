
<%@ page language="java" contentType="application/octet-stream" pageEncoding="UTF-8"%>
<%@ page import="java.security.SecureRandom"%>
<%

response.setStatus(200);

// Disable Compression
pageContext.getOut().clear();
response.setHeader("Content-Description", "File Transfer");
response.setHeader("Content-Disposition", "attachment; filename=random.dat");
response.setHeader("Content-Transfer-Encoding", "binary");

String corsParam = request.getParameter("cors");
if (corsParam != null) {
    response.setHeader("Access-Control-Allow-Origin", "*");
    response.setHeader("Access-Control-Allow-Methods", "GET, POST");
}

// Cache settings: never cache this request
response.setHeader("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, s-maxage=0");
response.setHeader("Cache-Control", "post-check=0, pre-check=0");
response.setHeader("Pragma", "no-cache");

// Determine how much data we should send
int chunks = getChunkCount( request );

// Generate data
SecureRandom random = new SecureRandom();
byte[] data = new byte[1048576];
random.nextBytes(data);

// Deliver chunks of 1048576 bytes
for (int i = 0; i < chunks; i++) {
    response.getOutputStream().write(data);
    response.getOutputStream().flush();
}


%>
<%!
public int getChunkCount( HttpServletRequest request ) {
  String ckSizeParam = request.getParameter("ckSize");
  if (ckSizeParam == null || !ckSizeParam.matches("\\d+") || Integer.parseInt(ckSizeParam) <= 0) {
      return 4;
  }
  int ckSize = Integer.parseInt(ckSizeParam);
  return Math.min(ckSize, 1024);
}
%>