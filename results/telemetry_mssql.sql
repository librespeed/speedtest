SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [dbo].[speedtest_users](
	[id] [bigint] IDENTITY(120,1) NOT NULL,
	[timestamp] [datetime] NOT NULL,
	[ip] [nvarchar](max) NOT NULL,
	[ispinfo] [nvarchar](max) NULL,
	[extra] [nvarchar](max) NULL,
	[ua] [nvarchar](max) NOT NULL,
	[lang] [nvarchar](max) NOT NULL,
	[dl] [nvarchar](max) NULL,
	[ul] [nvarchar](max) NULL,
	[ping] [nvarchar](max) NULL,
	[jitter] [nvarchar](max) NULL,
	[log] [nvarchar](max) NULL,
 CONSTRAINT [PK_speedtest_users] PRIMARY KEY CLUSTERED
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

ALTER TABLE [dbo].[speedtest_users] ADD  CONSTRAINT [DF_speedtest_users_timestamp]  DEFAULT (getdate()) FOR [timestamp]
GO


